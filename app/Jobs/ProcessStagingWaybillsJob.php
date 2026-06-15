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
        $now = now();

        DB::table('staging_all_waybills')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById($this->batchSize, function ($rows) use ($now) {

                $batchStart = microtime(true);

                $successInsert = [];
                $uploadDetailsInsert = [];
                $successIds = [];
                $failedIds = [];

                $batchProcessed = 0;
                $batchFailed = 0;

                /**
                 * STEP 1: PRE-NORMALIZE
                 */
                $normalized = [];

                foreach ($rows as $row) {

                    $outboundDate = $this->safeDate($row->outbound_date ?? null);
                    $deliveredDate = $this->safeDate($row->delivered_date ?? null);

                    $accountingDate = $this->getAccountingDate($outboundDate, $deliveredDate);

                    $normalized[] = [
                        'row' => $row,
                        'outboundDate' => $outboundDate,
                        'deliveredDate' => $deliveredDate,
                        'accountingDate' => $accountingDate,
                    ];
                }

                /**
                 * STEP 2: GROUP
                 */
                $groups = [];

                foreach ($normalized as $data) {
                    $groups[$data['accountingDate']][] = $data;
                }

                /**
                 * STEP 3: PROCESS
                 */
                foreach ($groups as $accountingDate => $items) {

                    foreach ($items as $item) {

                        $row = $item['row'];
                        $waybill = $row->waybill_no;

                        try {

                            $successInsert[] = [
                                'norefund_id' => $this->uploadId,
                                'waybill_no' => $waybill,
                                'outbound_date' => $item['outboundDate'],
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
                                'insurance_expense_amount' => 0,
                                'refund' => 0,
                                'service_type' => $row->service_type,
                                'waybill_status' => $row->waybill_status,
                                'confirm_date' => $row->confirm_date,
                                'delivered_date' => $item['deliveredDate'],
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

                            $failedIds[] = $row->id;
                            $batchFailed++;
                        }
                    }
                }

                /**
                 * STEP 4: TIMING SPLIT (IMPORTANT PART)
                 */

                // processing time (everything before DB writes)
                $processDuration = microtime(true) - $batchStart;

                /**
                 * DB WRITE START (ONLY INSERT TIME)
                 */
                $dbStart = microtime(true);

                $uploadDataDuration = 0;
                $uploadDetailsDuration = 0;

                DB::transaction(function () use (
                    $successInsert,
                    $uploadDetailsInsert,
                    $successIds,
                    $failedIds,
                    &$uploadDataDuration,
                    &$uploadDetailsDuration
                ) {

                    /**
                     * upload_data insert (TRACK ONLY THIS)
                     */
                    if (!empty($successInsert)) {

                        $t0 = microtime(true);

                        foreach (array_chunk($successInsert, 2000) as $chunk) {
                            DB::table('upload_data')->insert($chunk);
                        }

                        $uploadDataDuration = microtime(true) - $t0;

                        /*
                        Log::info('upload_data_insert_stats', [
                            'rows' => count($successInsert),
                            'duration' => round($uploadDataDuration, 4),
                        ]);
                        */
                    }

                    /**
                     * upload_details insert (LOG ONLY, NOT COUNTED)
                     */
                    if (!empty($uploadDetailsInsert)) {

                        $t1 = microtime(true);

                        foreach (array_chunk($uploadDetailsInsert, 2000) as $chunk) {
                            DB::table('upload_details')->insert($chunk);
                        }

                        $uploadDetailsDuration = microtime(true) - $t1;

                        /*
                        Log::info('upload_details_insert_stats', [
                            'rows' => count($uploadDetailsInsert),
                            'duration' => round($uploadDetailsDuration, 4),
                        ]);
                        */
                    }

                    /**
                     * staging update
                     */
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
                 * STEP 5: UPDATE uploads TABLE (ONLY upload_data INSERT TIME INCLUDED)
                 */
                DB::table('uploads')
                    ->where('id', $this->uploadId)
                    ->update([
                        'processed_rows' => DB::raw("processed_rows + {$batchProcessed}"),
                        'failed_rows' => DB::raw("failed_rows + {$batchFailed}"),

                        // ONLY THIS IS COUNTED
                        'processed_duration' => DB::raw(
                            "processed_duration + " . round($processDuration + $uploadDataDuration, 4)
                        ),

                        'updated_at' => $now,
                    ]);
            });

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

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