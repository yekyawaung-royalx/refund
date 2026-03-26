<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class ExportFileJob
{
    protected string $yearMonth;
    protected int $batchSize = 5000; // ✅ batch size for updating export_id

    public function __construct(?string $yearMonth = null)
    {
        $this->yearMonth = $yearMonth ?? Carbon::now()->format('Ym');
    }

    public function handle()
    {
        // -----------------------------
        // Start timer for performance measurement
        // -----------------------------
        $startTime = microtime(true);
        $startDt = Carbon::now();

        $timestamp = now()->timestamp;
        $today = Carbon::now()->format('Ymd-His');
        $fileName = "export-{$today}.csv";

        // -----------------------------
        // Create export record
        // -----------------------------
        $exportId = DB::table('exports')->insertGetId([
            'filename' => $fileName,
            'filepath' => '',
            'total_rows' => 0,
            'start_datetime' => $startDt,
            'end_datetime' => $startDt,
            'duration' => 'pending',
            'exported_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {

            // -----------------------------
            // Determine partitions
            // -----------------------------
            $current = Carbon::createFromFormat('Ym', $this->yearMonth);
            $previous = $current->copy()->subMonth();

            $currentPartition = "P" . $current->format('Ym');
            $previousPartition = "P" . $previous->format('Ym');

            $partitions = [$previousPartition, $currentPartition];

            $existingPartitions = DB::table('information_schema.PARTITIONS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'upload_data')
                ->whereIn('PARTITION_NAME', $partitions)
                ->pluck('PARTITION_NAME')
                ->toArray();

            if (empty($existingPartitions)) {
                throw new \Exception("Required partitions not found");
            }

            $partitionList = implode(',', $existingPartitions);

            // -----------------------------
            // ⚡ Build export query
            // -----------------------------
            $query = "
                SELECT 
                    id,
                    outbound_date,
                    delivered_date,
                    customer_reference_no,
                    customer,
                    waybill_no,
                    from_analytic_account,
                    to_analytic_account,
                    receiver_name,
                    weight,
                    cod_total_amount,
                    express_income_amount,
                    cod_express_income_amount,
                    cod_income_amount,
                    cod_payable_amount,
                    confirm_date,
                    from_city,
                    to_city,
                    service_type
                FROM upload_data PARTITION ({$partitionList})
                WHERE refund = 0
                ORDER BY id
            ";

            // -----------------------------
            // Prepare export file path
            // -----------------------------
            $folder = $current->format('Y-m');
            $directory = storage_path("app/private/exports/{$folder}");

            if (!is_dir($directory)) mkdir($directory, 0755, true);

            $relativePath = "private/exports/{$folder}/{$fileName}";
            $filePath = storage_path("app/{$relativePath}");

            $handle = fopen($filePath, 'w');
            if (!$handle) throw new \Exception("Unable to create export file");

            // -----------------------------
            // Write CSV header
            // -----------------------------
            fputcsv($handle, [
                'Outbound Date','Accounting Date','Sender/Internal Reference','Sender/Display Name',
                'Waybill No','From Analytic Account','To Analytic Account','Receiver Name',
                'Weight','Total','Insurance Amount','COD Express Income','COD Income','COD Payable',
                'Delivered Date','Confirm Date','From City','To City','Service Type'
            ]);

            $count = 0;
            $rowIds = [];

            // -----------------------------
            // Cursor-based export (memory-efficient)
            // -----------------------------
            foreach (DB::cursor($query) as $row) {

                fputcsv($handle, [
                    $row->outbound_date,
                    $row->delivered_date,
                    $row->customer_reference_no,
                    $row->customer,
                    $row->waybill_no,
                    $row->from_analytic_account,
                    $row->to_analytic_account,
                    $row->receiver_name,
                    $row->weight,
                    $row->cod_total_amount,
                    $row->express_income_amount,
                    $row->cod_express_income_amount,
                    $row->cod_income_amount,
                    $row->cod_payable_amount,
                    $row->delivered_date,
                    $row->confirm_date,
                    $row->from_city,
                    $row->to_city,
                    $row->service_type
                ]);

                $rowIds[] = $row->id;
                $count++;

                // -----------------------------
                // Batch update export_id in transaction
                // -----------------------------
                if ($count % $this->batchSize === 0) {
                    DB::transaction(function() use ($rowIds, $exportId) {
                        DB::table('upload_data')->whereIn('id', $rowIds)->update(['export_id' => $exportId]);
                    });
                    $rowIds = [];
                    fflush($handle);
                }
            }

            // -----------------------------
            // Update remaining rows
            // -----------------------------
            if (!empty($rowIds)) {
                DB::transaction(function() use ($rowIds, $exportId) {
                    DB::table('upload_data')->whereIn('id', $rowIds)->update(['export_id' => $exportId]);
                });
            }

            fclose($handle);

            // -----------------------------
            // Finalize export record
            // -----------------------------
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            $endDt = Carbon::now();

            DB::table('exports')->where('id', $exportId)->update([
                'filepath' => $relativePath,
                'total_rows' => $count,
                'end_datetime' => $endDt,
                'duration' => $duration . 's',
                'updated_at' => now(),
            ]);

            Log::info("Export completed: {$filePath} | Rows: {$count} | Duration: {$duration}s");
            
            DailyRefundSummaryJob::dispatch($exportId, $count, null, null);
            // -----------------------------
            // Notify user
            // -----------------------------
            Mail::to('yekyawaung1991@gmail.com')
                ->queue(new \App\Mail\ExportCompletedMail(
                    $this->yearMonth,
                    $count,
                    $duration,
                    $fileName,
                    $startDt->toDateTimeString(),
                    $endDt->toDateTimeString()
                ));

        } catch (\Throwable $e) {
            // -----------------------------
            // Error handling
            // -----------------------------
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            $endDt = Carbon::now();

            DB::table('exports')->where('id', $exportId)->update([
                'end_datetime' => $endDt,
                'duration' => $duration . 's',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // update refund summary table
            // DB::table('refund_summaries')->insert([
            //     'payment_date' => '',
            //     'to_refund_amount' => '',
            //     'to_refund_rows' => $count,
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ]);

            Log::error("Export failed: " . $e->getMessage());
            throw $e;
        }
    }
}