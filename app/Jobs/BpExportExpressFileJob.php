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

class BpExportExpressFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    protected string $from;
    protected string $to;


    public function __construct(
        string $from,
        string $to
    ) {
        $this->from = $from;
        $this->to = $to;
    }



    public function handle()
    {
        $startTime = microtime(true);
        $startDt = Carbon::now();
        $fileName = "audit-export-express-" . now()->format('Ymd-His') . ".csv";

        // =============================
        // STEP 1 COUNT
        // =============================

        $countQuery = "
            SELECT COUNT(*) as total
            FROM upload_data
            WHERE accounting_date BETWEEN ? AND ?
                AND customer_reference_no LIKE 'BP%'
                AND refund = 1
        ";

        $totalRows = DB::selectOne(
            $countQuery,
            [
                $this->from,
                $this->to
            ]
        )->total;

        if ($totalRows == 0) {
            Log::info("BP export skipped no data");
            return;
        }

        // =============================
        // CREATE EXPORT
        // =============================
        $exportId = DB::table('exports')->insertGetId([
            'filename' => $fileName,
            'filepath' => '',
            'service_type' => 'all',
            'total_rows' => 0,
            'start_datetime' => $startDt,
            'end_datetime' => $startDt,
            'duration' => 'pending',
            'exported_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {

            // =============================
            // FILE
            // =============================
            $folder = now()->format('Y-m');
            $directory = storage_path(
                "app/private/exports/{$folder}"
            );

            if (!is_dir($directory)) {
                mkdir($directory,0775,true);
            }

            $relativePath = "private/exports/{$folder}/{$fileName}";

            $filePath =
                storage_path("app/".$relativePath);

            $handle = fopen($filePath,'w');

            if(!$handle){
                throw new \Exception("Cannot create CSV");
            }

            fputcsv($handle,[
                'Outbound Date',
                'Accounting Date',
                'Sender/Internal Reference',
                'Sender/Display Name',
                'Waybill No',
                'From Analytic Account',
                'To Analytic Account',
                'Receiver Name',
                'Weight',
                'Total',
                'Insurance Amount',
                'COD Express Income',
                'COD Income',
                'COD Payable',
                'Delivered Date',
                'Confirm Date',
                'From City',
                'To City',
                'Service Type',
                'Waybill Status'
            ]);


            // =============================
            // STREAM QUERY
            // =============================
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
                FROM upload_data
                WHERE accounting_date BETWEEN ? AND ?
                    AND customer_reference_no LIKE 'BP%'
                    AND refund = 1
            ";
            $count = 0;

            foreach(
                DB::cursor(
                    $query,
                    [
                        $this->from,
                        $this->to
                    ]
                )
                as $row
            ){
                fputcsv($handle,[
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

                if($count % 5000 === 0){
                    fflush($handle);
                }
            }
            fclose($handle);
            // =============================
            // FINAL UPDATE
            // =============================


            DB::table('exports')
                ->where('id',$exportId)
                ->update([
                    'filepath'=>$relativePath,
                    'total_rows'=>$count,
                    'end_datetime'=>now(),
                    'duration'=>round(
                        microtime(true)-$startTime,
                        2
                    ).'s',
                    'updated_at'=>now()

                ]);

            Log::info("BP Export completed | Rows: {$count} | From: {$this->from} | To: {$this->to}");

        }catch(\Throwable $e){
            DB::table('exports')
                ->where('id',$exportId)
                ->update([
                    'end_datetime'=>now(),
                    'duration'=>round(
                        microtime(true)-$startTime,
                        2
                    ).'s',
                    'error_message'=>$e->getMessage(),
                    'updated_at'=>now()
                ]);

            Log::error(
                "BP Export failed ".$e->getMessage()
            );

            throw $e;

        }
    }
}