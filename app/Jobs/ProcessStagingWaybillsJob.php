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

        $totalProcessed = 0;
        $totalFailed = 0;

        $file = null;
        $fileOpened = false;
        $relativePath = null;

        DB::table('staging_all_waybills')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById($this->batchSize, function ($rows) use (
                $now,
                &$totalProcessed,
                &$totalFailed,
                &$file,
                &$fileOpened,
                &$relativePath
            ) {

                $batchStart = microtime(true);

                /**
                 * STEP 1: normalize + group safely
                 */
                $rowsByDate = $rows->groupBy(function ($row) {
                    return $this->getAccountingDate(
                        $this->safeDate($row->outbound_date ?? null),
                        $this->safeDate($row->delivered_date ?? null)
                    );
                });

                $successInsert = [];
                $uploadDetailsInsert = [];
                $successIds = [];
                $failedIds = [];

                $batchProcessed = 0;
                $batchFailed = 0;

                foreach ($rowsByDate as $accountingDate => $groupRows) {

                    $waybills = $groupRows
                        ->pluck('waybill_no')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    /**
                     * STEP 2: partition scoped duplicate check
                     */
                    $checkStart = microtime(true);
                    $existingWaybills = DB::table('upload_data')
                        ->where('accounting_date', $accountingDate)
                        ->whereIn('waybill_no', $waybills)
                        ->pluck('waybill_no')
                        ->flip()
                        ->toArray();

                    Log::info('duplicate_check_time', [
                        'accounting_date' => $accountingDate,
                        'count' => count($waybills),
                        'duration' => round(microtime(true) - $checkStart, 4),
                    ]);

                    // Log::info('partition_scan', [
                    //     'upload_id' => $this->uploadId,
                    //     'accounting_date' => $accountingDate,
                    //     'partition' => 'P' . date('Ym', strtotime($accountingDate)),
                    //     'waybill_count' => count($waybills),
                    // ]);

                    foreach ($groupRows as $row) {

                        $waybill = $row->waybill_no;

                        if (isset($existingWaybills[$waybill])) {

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

                        try {
                            $outboundDate = $this->safeDate($row->outbound_date ?? null);
                            $deliveredDate = $this->safeDate($row->delivered_date ?? null);
                            $accountingDate = $this->getAccountingDate($outboundDate, $deliveredDate);

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
                 * STEP 3: DB transaction
                 */
                DB::transaction(function () use (
                    $successInsert,
                    $uploadDetailsInsert,
                    $successIds,
                    $failedIds
                ) {

                    if (!empty($successInsert)) {
                        $insertStart = microtime(true); // processing start time

                        DB::table('upload_data')->insert($successInsert);

                        // processing log
                        Log::info('upload_data_insert_time', [
                            'rows' => count($successInsert),
                            'duration' => round(microtime(true) - $insertStart, 4),
                        ]);
                    }

                    if (!empty($uploadDetailsInsert)) {
                        $detailInsertStart = microtime(true); // processing start time

                        DB::table('upload_details')->insert($uploadDetailsInsert);

                        // processing log
                        Log::info('upload_details_insert_time', [
                            'rows' => count($uploadDetailsInsert),
                            'duration' => round(microtime(true) - $detailInsertStart, 4),
                        ]);
                    }

                    if (!empty($successIds)) {
                        $updateStart = microtime(true); // processing start time

                        DB::table('staging_all_waybills')
                            ->whereIn('id', $successIds)
                            ->update(['status' => 'processed']);

                        // processing log
                        Log::info('status_update_time', [
                            'rows' => count($successIds),
                            'duration' => round(microtime(true) - $updateStart, 4),
                        ]);
                    }

                    if (!empty($failedIds)) {
                        DB::table('staging_all_waybills')
                            ->whereIn('id', $failedIds)
                            ->update(['status' => 'failed']);
                    }
                });

                /**
                 * STEP 4: correct counters update
                 */
                $totalProcessed += $batchProcessed;
                $totalFailed += $batchFailed;

                $batchDuration = round(microtime(true) - $batchStart, 2);

                // DB::table('uploads')
                //     ->where('id', $this->uploadId)
                //     ->update([
                //         'processed_duration' => DB::raw("processed_duration + {$batchDuration}"),
                //         'processed_rows' => DB::raw("processed_rows + {$batchProcessed}"),
                //         'failed_rows' => DB::raw("failed_rows + {$batchFailed}"),
                //         'updated_at' => now(),
                //     ]);
            });

        if ($fileOpened && $file) {
            fclose($file);
        }

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'status' => 'completed',
                'processed_rows' => $totalProcessed,
                'failed_rows' => $totalFailed,
                'processed_duration' => round(microtime(true) - $startTime, 2),
                'failed_path' => $relativePath,
                'updated_at' => now(),
            ]);
        
        // Delete staging data
        $deleteStart = microtime(true);

        DB::table('staging_all_waybills')
            ->where('upload_id', $this->uploadId)
            ->whereIn('status', ['processed', 'failed'])
            ->delete();

        Log::info('staging_delete_time', [
            'duration' => round(microtime(true) - $deleteStart, 2),
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
}