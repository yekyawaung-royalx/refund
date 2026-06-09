<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExportSameDayFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $yearMonth;

    public function __construct(?string $yearMonth = null)
    {
        $this->yearMonth = $yearMonth ?? Carbon::now()->format('Ym');
    }

    public function handle()
    {
        $startTime = microtime(true);
        $startDt = Carbon::now();
        $exportStartedAt = Carbon::now();

        $fileName = "export-same-day-" . now()->format('Ymd-His') . ".csv";

        // -----------------------------
        // Partition selection
        // -----------------------------
        $current = Carbon::createFromFormat('Ym', $this->yearMonth);

        $partitions = collect(range(0, 2))
            ->map(fn ($i) => "P" . $current->copy()->subMonths($i)->format('Ym'))
            ->toArray();

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
        // STEP 1: COUNT FIRST (UX SAFE)
        // -----------------------------
        $countQuery = "
            SELECT COUNT(*) as total
            FROM upload_data PARTITION ({$partitionList})
            WHERE refund = 0
                AND payment_by = 'Sender Pay'
                AND payment_type = 'Postpaid'
                AND service_type = 'same_day_delivery'
        ";

        $totalRows = DB::selectOne($countQuery)->total;

        if ($totalRows == 0) {
            Log::info("Export skipped (no data found)");
            return;
        }

        // -----------------------------
        // STEP 2: CREATE EXPORT RECORD ONLY IF DATA EXISTS
        // -----------------------------
        $exportId = DB::table('exports')->insertGetId([
            'filename' => $fileName,
            'filepath' => '',
            'service_type' => 'same-day-delivery',
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
            // FILE SETUP
            // -----------------------------
            $folder = $current->format('Y-m');
            $directory = storage_path("app/private/exports/{$folder}");

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $relativePath = "private/exports/{$folder}/{$fileName}";
            $filePath = storage_path("app/{$relativePath}");

            $handle = fopen($filePath, 'w');

            if (!$handle) {
                throw new \Exception("Cannot create CSV file");
            }

            // CSV HEADER
            fputcsv($handle, [
                'Outbound Date','Accounting Date','Sender/Internal Reference','Sender/Display Name',
                'Waybill No','From Analytic Account','To Analytic Account','Receiver Name',
                'Weight','Total','Insurance Amount','COD Express Income','COD Income','COD Payable',
                'Delivered Date','Confirm Date','From City','To City','Service Type','Waybill Status'
            ]);

            // -----------------------------
            // STEP 3: STREAM EXPORT (FAST)
            // -----------------------------
            $query = "
                SELECT 
                    id,
                    outbound_date,
                    accounting_date,
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
                    delivered_date,
                    confirm_date,
                    from_city,
                    to_city,
                    service_type,
                    waybill_status
                FROM upload_data PARTITION ({$partitionList})
                WHERE refund = 0
                    AND payment_by = 'Sender Pay'
                    AND payment_type = 'Postpaid'
                    AND service_type = 'same_day_delivery'
            ";

            $count = 0;
            $batchSize = 5000;

            foreach (DB::cursor($query) as $row) {

                fputcsv($handle, [
                    $row->outbound_date,
                    $row->accounting_date,
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
                    $row->service_type,
                    $row->waybill_status
                ]);

                $count++;

                if ($count % $batchSize === 0) {
                    fflush($handle);
                }
            }

            fclose($handle);

            // -----------------------------
            // STEP 4: BULK UPDATE export_id (OVERWRITE MODE)
            // -----------------------------
            DB::statement("
                UPDATE upload_data PARTITION ({$partitionList})
                SET export_id = ?
                WHERE refund = 0
                    AND payment_by = 'Sender Pay'
                    AND payment_type = 'Postpaid'
                    AND service_type = 'same_day_delivery'
            ", [$exportId]);

            // -----------------------------
            // STEP 5: FINALIZE EXPORT
            // -----------------------------
            DB::table('exports')
                ->where('id', $exportId)
                ->update([
                    'filepath' => $relativePath,
                    'total_rows' => $count,
                    'end_datetime' => now(),
                    'duration' => round(microtime(true) - $startTime, 2) . 's',
                    'updated_at' => now(),
                ]);

            Log::info("Export completed | Rows: {$count} | ExportID: {$exportId}");

        } catch (\Throwable $e) {

            DB::table('exports')->where('id', $exportId)->update([
                'end_datetime' => now(),
                'duration' => round(microtime(true) - $startTime, 2) . 's',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            Log::error("Export failed: " . $e->getMessage());

            throw $e;
        }
    }
}