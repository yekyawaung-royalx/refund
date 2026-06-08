<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\AutoCheckAnalyticBranchJob;

class ProcessStagingWaybillsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;
    protected string $category;
    protected int $batchSize = 1000;

    public function __construct(int $uploadId, string $category)
    {
        $this->uploadId = $uploadId;
        $this->category = $category;
    }

    public function handle()
    {
        $startTime = microtime(true);
        $now = now();

        $processed = 0;
        $failed = 0;

        $file = null;
        $fileOpened = false;
        $relativePath = null;

        $analyticsMap = DB::table('analytics')
            ->pluck('account', 'reference')
            ->toArray();

        // =========================================
        // IMPORTANT: DO NOT LOAD MILLION RECORDS
        // =========================================
        // better approach: index-based lookup only
        // (DO NOT pluck all upload_data)
        DB::table('staging_all_waybills')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById($this->batchSize, function ($rows) use (
                $now,
                $analyticsMap,
                &$processed,
                &$failed,
                &$file,
                &$fileOpened,
                &$relativePath
            ) {
                $batchStart = microtime(true);
                $successInsert = [];
                $uploadDetailsInsert = [];
                $successIds = [];

                $waybills = collect($rows)
                    ->pluck('waybill_no')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $existingWaybills = DB::table('upload_data')
                    ->whereIn('waybill_no', $waybills)
                    ->pluck('waybill_no')
                    ->flip()
                    ->toArray();

                $failedIds = [];

                foreach ($rows as $row) {

                    $data = json_decode($row->row_data, true);
                    $waybill = $row->waybill_no;

                    // analytic account lookup
                    $originBranch = trim($data[11] ?? '');
                    $destinationBranch = trim($data[13] ?? '');

                    $fromAnalytic =
                        $analyticsMap[$originBranch] ?? null;

                    $toAnalytic =
                        $analyticsMap[$destinationBranch] ?? null;

                    // =========================
                    // FAST DUPLICATE CHECK (INDEX REQUIRED)
                    // =========================
                    if (isset($existingWaybills[$waybill])) {

                        $this->writeFailedFile(
                            $file,
                            $fileOpened,
                            $relativePath,
                            $waybill,
                            'DUPLICATE'
                        );

                        $failedIds[] = $row->id;

                        $failed++;
                        continue;
                    }

                    try {

                        $outboundDate = $this->safeDate($data[1] ?? null);
                        $deliveredDate = $this->safeDate($data[31] ?? null);

                        $accountingDate = $this->getAccountingDate($outboundDate, $deliveredDate);

                        // =========================
                        // upload_data
                        // =========================
                        $successInsert[] = [
                            'norefund_id' => $this->uploadId,
                            'waybill_no' => $waybill,
                            'outbound_date' => $outboundDate,
                            'customer_reference_no' => $data[3] ?? null,
                            'customer' => $data[4] ?? null,
                            'from_city' => $data[10] ?? null,
                            'origin_branch' => $data[11] ?? null,
                            'to_city' => $data[12] ?? null,
                            'destination_branch' => $data[13] ?? null,
                            'from_analytic_account' => $fromAnalytic,
                            'to_analytic_account' => $toAnalytic,
                            'receiver_name' => $this->clean($data[15] ?? null, 255),
                            'payment_by' => $data[20] ?? null,
                            'payment_type' => $data[21] ?? null,
                            'service' => $data[22] ?? null,
                            'weight' => isset($data[23]) ? (float)$data[23] : null,
                            'express_income_amount' => isset($data[24]) ? (float)$data[24] : null,
                            'cod_total_amount' => isset($data[25]) ? (float)$data[25] : null,
                            'cod_express_income_amount' => isset($data[26]) ? (float)$data[26] : null,
                            'cod_income_amount' => isset($data[27]) ? (float)$data[27] : null,
                            'cod_payable_amount' => isset($data[28]) ? (float)$data[28] : null,
                            'insurance_expense_amount' => '0.0',
                            'refund' => 0,
                            'service_type' => $data[29] ?? null,
                            'waybill_status' => $this->safeStatus($data[30] ?? null),
                            'confirm_date' => $data[32] ? date('Y-m-d', strtotime($data[32])) : null,
                            'delivered_date' => $deliveredDate,
                            'accounting_date' => $accountingDate,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        // =========================
                        // upload_details table (NEW FEATURE)
                        // =========================
                        $uploadDetailsInsert[] = [
                            'waybill_no' => $waybill, // fill after insert
                            'customer_order_reference' => $data[2] ?? null,
                            'phone' => $data[5] ?? null,
                            'mobile' => $data[6] ?? null,
                            'operator' => $data[7] ?? null,
                            'pickup_man' => $data[8] ?? null,
                            'other' => $data[14] ?? null,
                            'receiver_mobile' => $this->clean($data[16] ?? null, 255),
                            'receiver_address' => $this->clean($data[17] ?? null, 255),
                            'recipient_name' => $this->clean($data[18] ?? null, 255),
                            'recipient_phone' => $this->clean($data[19] ?? null, 255),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $successIds[] = $row->id;
                        $processed++;
                    } catch (\Throwable $e) {

                        $this->writeFailedFile(
                            $file,
                            $fileOpened,
                            $relativePath,
                            $waybill,
                            $e->getMessage()
                        );

                        $failedIds[] = $row->id;

                        $failed++;
                    }
                }

                // =========================
                // BULK INSERT upload_data
                // =========================
                DB::transaction(function () use ($successInsert, $successIds, $failedIds, $uploadDetailsInsert) {
                    if (!empty($successInsert)) {

                        DB::table('upload_data')->upsert(
                            $successInsert,
                            ['waybill_no']
                        );

                        DB::table('upload_details')->upsert(
                            $uploadDetailsInsert,
                            ['waybill_no']
                        );
                    }

                    if (!empty($successIds)) {
                        DB::table('staging_all_waybills')
                            ->whereIn('id', $successIds)
                            ->update(['status' => 'processed']);
                    }

                    if (!empty($failedIds)) {
                        DB::table('staging_all_waybills')
                            ->whereIn('id', $failedIds)
                            ->update(['status' => 'failed']);
                    }
                });

                $batchDuration = round(microtime(true) - $batchStart, 2);

                DB::table('uploads')
                    ->where('id', $this->uploadId)
                    ->update([
                        'processed_duration' => DB::raw("processed_duration + {$batchDuration}"),
                        'processed_rows' => DB::raw("processed_rows + " . count($successIds)),
                        'failed_rows' => DB::raw("failed_rows + " . count($failedIds)),
                        'updated_at' => now(),
                    ]);
            });

        // =========================
        // CLOSE FILE
        // =========================
        if ($fileOpened && $file) {
            fclose($file);
        }

        // =========================
        // CLEANUP (SAFER: only processed/failed)
        // =========================
        DB::table('staging_all_waybills')
            ->where('upload_id', $this->uploadId)
            ->whereIn('status', ['processed', 'failed'])
            ->delete();

        // =========================
        // FINAL UPDATE
        // =========================
        $endTime = microtime(true);

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'status' => 'completed',
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'processed_duration' => round($endTime - $startTime, 2),
                'failed_path' => $relativePath,
                'updated_at' => $now,
            ]);

    }

    // =========================
    // FAILED FILE
    // =========================
    private function writeFailedFile(&$file, &$opened, &$path, $waybill, $reason)
    {
        if (!$opened) {

            $folder = now()->format('Y-m');
            $dir = storage_path("app/private/refund-failed/{$folder}");

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $fileName = "failed_upload_{$this->uploadId}.csv";
            $fullPath = "{$dir}/{$fileName}";

            $path = "refund-failed/{$folder}/{$fileName}";

            $file = fopen($fullPath, 'w');
            fputcsv($file, ['waybill_no', 'reason']);

            $opened = true;
        }

        fputcsv($file, [$waybill, $reason]);
    }

    // =========================
    // HELPERS
    // =========================
    private function safeDate($v): ?string
    {
        return empty($v) ? null : date('Y-m-d', strtotime($v));
    }

    private function getAccountingDate($o, $d): ?string
    {
        return match ($this->category) {
            'sender-prepaid' => $o,
            'sender-postpaid', 'receiver-postpaid', 'sender-foc' => $d,
            default => null,
        };
    }

    private function safeStatus($s): ?string
    {
        $s = strtoupper(trim((string)$s));
        return in_array($s, ['DELIVERED','RETURNED','CANCELLED','COMPLETED','REJECTED']) ? $s : null;
    }

    private function clean($v, $len = null)
    {
        if ($v === null) return null;
        $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v);
        return $len ? mb_substr(trim($v), 0, $len) : trim($v);
    }
}