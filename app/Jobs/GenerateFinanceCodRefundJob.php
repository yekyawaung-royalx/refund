<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class GenerateFinanceCodRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    protected string $paymentDate;
    protected string $category;
    protected string $exportedBy;


    public $timeout = 1800;
    public $tries = 3;


    public function __construct(
        string $paymentDate,
        string $category = 'all',
        string $exportedBy = 'system'
    ) {
        $this->paymentDate = $paymentDate;
        $this->category = $category;
        $this->exportedBy = $exportedBy;
    }



    public function handle(): void
    {

        $startTime = microtime(true);


        try {


            $headerRow = [
                'Account',
                'Partner',
                'Analytic Account',
                'Date',
                'Due Date',
                'Debit',
                'Credit',
                'Operating Unit',
                'Label',
            ];



            /*
            |--------------------------------------------------------------------------
            | Category Filter
            |--------------------------------------------------------------------------
            */

            $exportVendor =
                $this->category === 'all'
                ||
                $this->category === 'vendor';


            $exportInvoice =
                $this->category === 'all'
                ||
                $this->category === 'invoice';



            /*
            |--------------------------------------------------------------------------
            | Check Data Exists
            |--------------------------------------------------------------------------
            */


            $checkQuery = DB::table('upload_data')
                ->whereDate(
                    'payment_date',
                    $this->paymentDate
                )
                ->where('refund',1)
                ->where('cod_refund_export',0);



            if($this->category === 'vendor'){

                $checkQuery->where(
                    'vendor_type',
                    'Vendor'
                );

            }
            elseif($this->category === 'invoice'){

                $checkQuery->where(
                    'vendor_type',
                    'Invoice'
                );

            }
            else {

                $checkQuery->whereIn(
                    'vendor_type',
                    [
                        'Vendor',
                        'Invoice'
                    ]
                );

            }



            if(!$checkQuery->exists()){


                DB::table('action_logs')
                    ->insert([

                        'action'=>'EXPORT',
                        'keywords'=>'COD_REFUND',
                        'user'=>$this->exportedBy,
                        'log'=>"No data found for {$this->paymentDate}",
                        'created_at'=>now(),
                        'updated_at'=>now(),

                    ]);


                Log::info(
                    "No COD refund data {$this->paymentDate}"
                );


                return;

            }





            /*
            |--------------------------------------------------------------------------
            | Vendor Summary
            |--------------------------------------------------------------------------
            */


            $vendor = DB::table('upload_data')

                ->selectRaw("

                    SUM(
                        CASE
                            WHEN service_type='express'
                            AND (
                                customer_reference_no LIKE 'E%'
                                OR customer_reference_no LIKE 'PUB%'
                                OR customer_reference_no LIKE 'PJ%'
                                OR customer_reference_no LIKE 'PT%'
                            )
                            THEN cod_payable_amount
                            ELSE 0
                        END
                    ) group1,


                    SUM(
                        CASE
                            WHEN service_type='express'
                            AND customer_reference_no LIKE 'BP%'
                            THEN cod_payable_amount
                            ELSE 0
                        END
                    ) group2,


                    SUM(
                        CASE
                            WHEN service_type='same_day_delivery'
                            THEN cod_payable_amount
                            ELSE 0
                        END
                    ) group3

                ")

                ->whereDate(
                    'payment_date',
                    $this->paymentDate
                )

                ->where('refund',1)

                ->where(
                    'vendor_type',
                    'Vendor'
                )

                ->where(
                    'cod_refund_export',
                    0
                )

                ->first();





            $group1 = (float)($vendor->group1 ?? 0);
            $group2 = (float)($vendor->group2 ?? 0);
            $group3 = (float)($vendor->group3 ?? 0);

            $vendorTotal =
                $group1+
                $group2+
                $group3;







            /*
            |--------------------------------------------------------------------------
            | Invoice Summary
            |--------------------------------------------------------------------------
            */


            $invoice = DB::table('upload_data')

                ->selectRaw("

                    SUM(
                        CASE
                            WHEN service_type='express'
                            AND (
                                customer_reference_no LIKE 'E%'
                                OR customer_reference_no LIKE 'PUB%'
                                OR customer_reference_no LIKE 'PJ%'
                                OR customer_reference_no LIKE 'PT%'
                            )
                            THEN cod_payable_amount
                            ELSE 0
                        END
                    ) group1,


                    SUM(
                        CASE
                            WHEN service_type='express'
                            AND customer_reference_no LIKE 'BP%'
                            THEN cod_payable_amount
                            ELSE 0
                        END
                    ) group2,


                    SUM(
                        CASE
                            WHEN service_type='same_day_delivery'
                            THEN cod_payable_amount
                            ELSE 0
                        END
                    ) group3

                ")


                ->whereDate(
                    'payment_date',
                    $this->paymentDate
                )

                ->where('refund',1)

                ->where(
                    'vendor_type',
                    'Invoice'
                )

                ->where(
                    'cod_refund_export',
                    0
                )

                ->first();




            $invoiceGroup1 =
                (float)($invoice->group1 ?? 0);

            $invoiceGroup2 =
                (float)($invoice->group2 ?? 0);

            $invoiceGroup3 =
                (float)($invoice->group3 ?? 0);


            $invoiceTotal =
                $invoiceGroup1+
                $invoiceGroup2+
                $invoiceGroup3;






            /*
            |--------------------------------------------------------------------------
            | Build Rows
            |--------------------------------------------------------------------------
            */


            $rows=[];


            $rows[]=$headerRow;




            if($exportVendor){


                $rows[]=[
                    '231604',
                    '',
                    'YGN',
                    $this->paymentDate,
                    $this->paymentDate,
                    '0.00',
                    number_format($group1,2,'.',''),
                    'OPR',
                    "{$this->paymentDate} Vendor Refund E/PUB/PJ/PT CA-Cash In Hand E Code Interim"
                ];


                $rows[]=[
                    '231600',
                    '',
                    'YGN',
                    $this->paymentDate,
                    $this->paymentDate,
                    '0.00',
                    number_format($group2,2,'.',''),
                    'OPR',
                    "{$this->paymentDate} Vendor Refund BP CA-Cash In Hand BPAZ Interim"
                ];



                $rows[]=[
                    '231606',
                    '',
                    'YGN',
                    $this->paymentDate,
                    $this->paymentDate,
                    '0.00',
                    number_format($group3,2,'.',''),
                    'OPR',
                    "{$this->paymentDate} Vendor Refund Same Day CA-Cash In Hand Same Day Interim A/C"
                ];



                $rows[]=[
                    '355003',
                    '',
                    'YGN',
                    $this->paymentDate,
                    $this->paymentDate,
                    number_format($vendorTotal,2,'.',''),
                    '0.00',
                    'OPR',
                    "{$this->paymentDate} Vendor Refund CL-Payable - Last Mile (New)"
                ];

            }





            if($exportInvoice){


                $rows[]=[
                    '231604',
                    '',
                    'YGN',
                    $this->paymentDate,
                    $this->paymentDate,
                    '0.00',
                    number_format(abs($invoiceGroup1),2,'.',''),
                    'OPR',
                    "{$this->paymentDate} Invoice Refund E/PUB/PJ/PT CA-Cash In Hand E Code Interim"
                ];


                $rows[]=[
                    '231600',
                    '',
                    'YGN',
                    $this->paymentDate,
                    $this->paymentDate,
                    '0.00',
                    number_format(abs($invoiceGroup2),2,'.',''),
                    'OPR',
                    "{$this->paymentDate} Invoice Refund BP CA-Cash In Hand BPAZ Interim"
                ];



                $rows[]=[
                    '231606',
                    '',
                    'YGN',
                    $this->paymentDate,
                    $this->paymentDate,
                    '0.00',
                    number_format(abs($invoiceGroup3),2,'.',''),
                    'OPR',
                    "{$this->paymentDate} Invoice Refund Same Day CA-Cash In Hand Same Day Interim A/C"
                ];



                $rows[]=[
                    '272750',
                    '',
                    'YGN',
                    $this->paymentDate,
                    $this->paymentDate,
                    number_format(abs($invoiceTotal),2,'.',''),
                    '0.00',
                    'OPR',
                    "{$this->paymentDate} Invoice Refund CL-Payable - Last Mile (Receivable)"
                ];

            }






            /*
            |--------------------------------------------------------------------------
            | Create XLSX
            |--------------------------------------------------------------------------
            */


            $folder = now()->format('Y-m');


            $fileName =
                "cod-refund-{$this->paymentDate}-".
                now()->format('Ymd_His').
                ".xlsx";


            $directory =
                storage_path(
                    "app/private/finance-reports/{$folder}"
                );


            if(!is_dir($directory)){

                mkdir(
                    $directory,
                    0755,
                    true
                );

            }


            $filePath =
                "{$directory}/{$fileName}";




            $spreadsheet = new Spreadsheet();

            $sheet =
                $spreadsheet->getActiveSheet();


            $sheet->setTitle(
                'COD Refund'
            );



            $rowIndex=1;


            foreach($rows as $row){


                $sheet->setCellValueExplicit(
                    "A{$rowIndex}",
                    (string)$row[0],
                    DataType::TYPE_STRING
                );


                $sheet->fromArray(
                    array_slice($row,1),
                    null,
                    "B{$rowIndex}"
                );


                $rowIndex++;

            }



            foreach(range('A','I') as $col){

                $sheet
                    ->getColumnDimension($col)
                    ->setAutoSize(true);

            }


            $sheet->freezePane('A2');

            $sheet->setAutoFilter(
                'A1:I1'
            );




            $writer =
                new Xlsx($spreadsheet);


            $writer->save($filePath);


            $spreadsheet->disconnectWorksheets();

            unset($spreadsheet);






            /*
            |--------------------------------------------------------------------------
            | DB Transaction
            |--------------------------------------------------------------------------
            */


            DB::transaction(function() use(
                $fileName,
                $folder,
                $rows,
                $startTime
            ){


                $exportId =
                    DB::table('finance_exports')
                    ->insertGetId([


                        'filename'=>$fileName,

                        'filepath'=>
                        "private/finance-reports/{$folder}/{$fileName}",


                        'report_date'=>$this->paymentDate,

                        'report_type'=>'cod-refund',

                        'category'=>$this->category,

                        'filtered'=>strtoupper($this->category),


                        'total_rows'=>count($rows),


                        'duration'=>
                        round(
                            microtime(true)-$startTime,
                            2
                        ),


                        'exported_by'=>$this->exportedBy,

                        'created_at'=>now(),

                        'updated_at'=>now(),

                    ]);






                $updateQuery =
                    DB::table('upload_data')

                    ->whereDate(
                        'payment_date',
                        $this->paymentDate
                    )

                    ->where(
                        'refund',
                        1
                    )

                    ->where(
                        'cod_refund_export',
                        0
                    );




                if($this->category==='vendor'){

                    $updateQuery
                        ->where(
                            'vendor_type',
                            'Vendor'
                        );

                }
                elseif($this->category==='invoice'){

                    $updateQuery
                        ->where(
                            'vendor_type',
                            'Invoice'
                        );

                }
                else{

                    $updateQuery
                        ->whereIn(
                            'vendor_type',
                            [
                                'Vendor',
                                'Invoice'
                            ]
                        );

                }





                $updatedRows =
                    $updateQuery->update([

                        'cod_refund_export'=>$exportId,

                        'updated_at'=>now(),

                    ]);







                DB::table('action_logs')
                    ->insert([


                        'action'=>'EXPORT',

                        'keywords'=>'COD_REFUND',

                        'user'=>$this->exportedBy,


                        'log'=>
                        "COD Refund completed | ".
                        "Date {$this->paymentDate} | ".
                        "Category {$this->category} | ".
                        "Rows {$updatedRows}",


                        'created_at'=>now(),

                        'updated_at'=>now(),


                    ]);






                Log::info(
                    "COD Refund Export Completed | ".
                    "Category {$this->category} | ".
                    "Rows {$updatedRows} | ".
                    "Time ".
                    round(
                        microtime(true)-$startTime,
                        2
                    )
                    ." sec"
                );



            });



        }
        catch(\Throwable $e){


            Log::error(
                "COD Refund Job Failed | ".
                $e->getMessage()
            );


            throw $e;

        }

    }

}