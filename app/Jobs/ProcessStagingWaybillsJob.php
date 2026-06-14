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
    protected int $batchSize = 2000;

    public function __construct(int $uploadId, string $category)
    {
        $this->uploadId = $uploadId;
        $this->category = $category;
    }

    public function handle()
    {
        $startTime = microtime(true);
        $now = now();

        $file = null;
        $fileOpened = false;
        $relativePath = null;

        DB::table('staging_all_waybills')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById($this->batchSize, function ($rows) use (
                $now,
                &$file,
                &$fileOpened,
                &$relativePath
            ) {

                $batchStart = microtime(true);

                $successInsert = [];
                $uploadDetailsInsert = [];
                $successIds = [];
                $failedIds = [];

                $batchProcessed = 0;
                $batchFailed = 0;

                /**
                 * STEP 1: normalize + group
                 */
                $rowsByDate = $rows->groupBy(function ($row) {
                    return $this->getAccountingDate(
                        $this->safeDate($row->outbound_date ?? null),
                        $this->safeDate($row->delivered_date ?? null)
                    );
                });

                foreach ($rowsByDate as $accountingDate => $groupRows) {

                    $waybills = $groupRows
                        ->pluck('waybill_no')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    /**
                     * STEP 2: duplicate check (ONE QUERY ONLY)
                     */
                    $existing = DB::table('upload_data')
                        ->where('accounting_date', $accountingDate)
                        ->whereIn('waybill_no', $waybills)
                        ->pluck('waybill_no')
                        ->flip()
                        ->toArray();

                    foreach ($groupRows as $row) {

                        $waybill = $row->waybill_no;

                        $outboundDate = $this->safeDate($row->outbound_date ?? null);
                        $deliveredDate = $this->safeDate($row->delivered_date ?? null);
                        $accountingDate = $this->getAccountingDate($outboundDate, $deliveredDate);

                        /**
                         * ❌ DUPLICATE CASE
                         */
                        if (isset($existing[$waybill])) {

                            $this->writeFailedFile(
                                $file,
                                $fileOpened,
                                $relativePath,
                                $waybill,
                                'DUPLICATE'
                            );

                            $failedIds[] = $row->id;
                            $batchFailed++;

                            continue;
                        }

                        /**
                         * ✅ SUCCESS CASE
                         */
                        try {

                            $successInsert[] = [
                                'norefund_id' => $this->uploadId,
                                'waybill_no' => $waybill,
                                'outbound_date' => $outboundDate,
                                'customer_reference_no' => $row->customer_reference_no ?? null,
                                'customer' => $row->customer ?? null,
                                'from_city' => $row->from_city ?? null,
                                'origin_branch' => $row->origin_branch ?? null,
                                'to_city' => $row->to_city ?? null,
                                'destination_branch' => $row->destination_branch ?? null,
                                'from_analytic_account' => $row->from_analytic_account,
                                'to_analytic_account' => $row->to_analytic_account,
                                'receiver_name' => $row->receiver_name,
                                'payment_by' => $row->payment_by,
                                'payment_type' => $row->payment_type,
                                'service' => $row->service,
                                'weight' => $row->weight,
                                'express_income_amount' => $row->express_income_amount,
                                'cod_total_amount' => $row->cod_total_amount,
                                'cod_express_income_amount' => $row->cod_express_income_amount,
                                'cod_income_amount' => $row->cod_income_amount,
                                'cod_payable_amount' => $row->cod_payable_amount,
                                'insurance_expense_amount' => $row->insurance_expense_amount,
                                'refund' => 0,
                                'service_type' => $row->service_type,
                                'waybill_status' => $row->waybill_status,
                                'confirm_date' => $row->confirm_date,
                                'delivered_date' => $deliveredDate,
                                'accounting_date' => $accountingDate,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];

                            $uploadDetailsInsert[] = [
                                'upload_id' => $this->uploadId,
                                'waybill_no' => $waybill,
                                'customer_order_reference' => $row->customer_order_reference,
                                'phone' => $row->phone,
                                'mobile' => $row->mobile,
                                'operator' => $row->operator,
                                'pickup_man' => $row->pickup_man,
                                'other' => $row->other,
                                'receiver_mobile' => $row->receiver_mobile,
                                'receiver_address' => $row->receiver_address,
                                'recipient_name' => $row->recipient_name,
                                'recipient_phone' => $row->recipient_phone,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];

                            $successIds[] = $row->id;
                            $batchProcessed++;

                        } catch (\Throwable $e) {

                            $this->writeFailedFile(
                                $file,
                                $fileOpened,
                                $relativePath,
                                $waybill,
                                $e->getMessage()
                            );

                            $failedIds[] = $row->id;
                            $batchFailed++;
                        }
                    }
                }

                /**
                 * STEP 3: DB TRANSACTION
                 */
                DB::transaction(function () use (
                    $successInsert,
                    $successIds,
                    $failedIds
                ) {

                    if (!empty($successInsert)) {
                        DB::table('upload_data')->insert($successInsert);
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

                /**
                 * STEP 4: DETAILS JOB (AFTER COMMIT SAFE)
                 */
                DB::afterCommit(function () use ($uploadDetailsInsert) {
                    if (!empty($uploadDetailsInsert)) {
                        InsertUploadDetailsJob::dispatch($uploadDetailsInsert);
                    }
                });

                /**
                 * STEP 5: ACCURATE COUNTERS (IMPORTANT FIX)
                 */
                DB::table('uploads')
                    ->where('id', $this->uploadId)
                    ->increment('processed_rows', $batchProcessed);

                DB::table('uploads')
                    ->where('id', $this->uploadId)
                    ->increment('failed_rows', $batchFailed);

                DB::table('uploads')
                    ->where('id', $this->uploadId)
                    ->increment('processed_duration', round(microtime(true) - $batchStart, 2));
            });

        /**
         * FINAL STATUS ONLY (NO COUNTER OVERRIDE)
         */
        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

        /**
         * CLEANUP STAGING
         */
        DB::table('staging_all_waybills')
            ->where('upload_id', $this->uploadId)
            ->whereIn('status', ['processed', 'failed'])
            ->delete();
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
}