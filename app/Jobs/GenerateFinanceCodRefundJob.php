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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class GenerateFinanceCodRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected string $paymentDate;
    protected ?string $category;
    protected string $exportedBy;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(string $paymentDate, ?string $category = 'all', string $exportedBy)
    {
        $this->paymentDate = $paymentDate;
        $this->category = $category ?? 'all';
        $this->exportedBy = $exportedBy;
    }

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            // ============================================
            // Check Data Exists
            // ============================================
            $hasData = DB::table('upload_data')
                ->whereDate('payment_date', $this->paymentDate)
                ->where('refund', 1)
                ->whereIn('vendor_type', ['Vendor', 'Invoice'])
                ->where('cod_refund_export', 0)
                ->exists();

                if (!$hasData) {
                    DB::table('action_logs')->insert([
                        'action'     => 'EXPORT',
                        'keywords'   => 'COD_REFUND',
                        'user'       => $this->exportedBy,
                        'log'        => "No data found for {$this->paymentDate}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info("No COD refund data found for {$this->paymentDate}");
                    return;
                }

                // ======================================================
                // Vendor Aggregate (1 Query Only)
                // ======================================================

                $vendor = DB::table('upload_data')
                    ->selectRaw("
                        SUM(
                            CASE
                                WHEN service_type = 'express'
                                AND (
                                    customer_reference_no LIKE 'E%'
                                    OR customer_reference_no LIKE 'PUB%'
                                    OR customer_reference_no LIKE 'PJ%'
                                    OR customer_reference_no LIKE 'PT%'
                                )
                                THEN cod_payable_amount
                                ELSE 0
                            END
                        ) AS group1,

                        SUM(
                            CASE
                                WHEN service_type = 'express'
                                AND customer_reference_no LIKE 'BP%'
                                THEN cod_payable_amount
                                ELSE 0
                            END
                        ) AS group2,

                        SUM(
                            CASE
                                WHEN service_type = 'same_day_delivery'
                                THEN cod_payable_amount
                                ELSE 0
                            END
                        ) AS group3
                    ")
                    ->whereDate('payment_date', $this->paymentDate)
                    ->where('refund', 1)
                    ->where('vendor_type', 'Vendor')
                    ->where('cod_refund_export', 0)
                    ->first();

                $group1 = (float) $vendor->group1;
                $group2 = (float) $vendor->group2;
                $group3 = (float) $vendor->group3;

                $totalCredit = $group1 + $group2 + $group3;

                // ======================================================
                // Invoice Aggregate (1 Query Only)
                // ======================================================
                $invoice = DB::table('upload_data')
                    ->selectRaw("
                        SUM(
                            CASE
                                WHEN service_type = 'express'
                                AND (
                                    customer_reference_no LIKE 'E%'
                                    OR customer_reference_no LIKE 'PUB%'
                                    OR customer_reference_no LIKE 'PJ%'
                                    OR customer_reference_no LIKE 'PT%'
                                )
                                THEN cod_payable_amount
                                ELSE 0
                            END
                        ) AS group1,

                        SUM(
                            CASE
                                WHEN service_type = 'express'
                                AND customer_reference_no LIKE 'BP%'
                                THEN cod_payable_amount
                                ELSE 0
                            END
                        ) AS group2,

                        SUM(
                            CASE
                                WHEN service_type = 'same_day_delivery'
                                THEN cod_payable_amount
                                ELSE 0
                            END
                        ) AS group3
                    ")
                    ->whereDate('payment_date', $this->paymentDate)
                    ->where('refund', 1)
                    ->where('vendor_type', 'Invoice')
                    ->where('cod_refund_export', 0)
                    ->first();

                $invoiceGroup1 = (float) $invoice->group1;
                $invoiceGroup2 = (float) $invoice->group2;
                $invoiceGroup3 = (float) $invoice->group3;

                $invoiceTotalCredit =
                    $invoiceGroup1 +
                    $invoiceGroup2 +
                    $invoiceGroup3;

                // ============================================
                // Build CSV Rows
                // ============================================
                $rows = [
                    [
                        (string) '231604',
                        '',
                        'YGN',
                        $this->paymentDate,
                        $this->paymentDate,
                        '0.00',
                        number_format((float) $group1, 2, '.', ''),
                        'OPR',
                        $this->paymentDate.' Vendor Refund E/PUB/PJ/PT CA-Cash In Hand E Code Interim'
                    ],

                    [
                        (string) '231600',
                        '',
                        'YGN',
                        $this->paymentDate,
                        $this->paymentDate,
                        '0.00',
                        number_format((float) $group2, 2, '.', ''),
                        'OPR',
                        $this->paymentDate.'Vendor Refund BP CA-Cash In Hand BPAZ Interim'
                    ],

                    [
                        (string) '231606',
                        '',
                        'YGN',
                        $this->paymentDate,
                        $this->paymentDate,
                        '0.00',
                        number_format((float) $group3, 2, '.', ''),
                        'OPR',
                        $this->paymentDate.' Vendor Refund Same Day CA-Cash In Hand Same Day Interim A/C'
                    ],

                    [
                        (string) '355003',
                        '',
                        'YGN',
                        $this->paymentDate,
                        $this->paymentDate,
                        number_format((float) $totalCredit, 2, '.', ''),
                        '0.00',
                        'OPR',
                        $this->paymentDate.' Invoice Refund CL-Payable - Last Mile (New)'
                    ],

                    [
                        (string) '231604',
                        '',
                        'YGN',
                        $this->paymentDate,
                        $this->paymentDate,
                        '0.00',
                        number_format(abs((float) $invoiceGroup1), 2, '.', ''),
                        'OPR',
                        $this->paymentDate.' Vendor Invoice E/PUB/PJ/PT CA-Cash In Hand E Code Interim'
                    ],

                    [
                        (string) '231600',
                        '',
                        'YGN',
                        $this->paymentDate,
                        $this->paymentDate,
                        '0.00',
                        number_format(abs((float) $invoiceGroup2), 2, '.', ''),
                        'OPR',
                        $this->paymentDate.' Invoice Refund BP CA-Cash In Hand BPAZ Interim'
                    ],

                    [
                        (string) '231606',
                        '',
                        'YGN',
                        $this->paymentDate,
                        $this->paymentDate,
                        '0.00',
                        number_format(abs((float) $invoiceGroup3), 2, '.', ''),
                        'OPR',
                        $this->paymentDate.' Invoice Refund Same Day CA-Cash In Hand Same Day Interim A/C'
                    ],

                    [
                        (string) '272750',
                        '',
                        'YGN',
                        $this->paymentDate,
                        $this->paymentDate,
                        number_format(abs((float) $invoiceTotalCredit), 2, '.', ''),
                        '0.00',
                        'OPR',
                        $this->paymentDate.' Invoice Refund CL-Payable - Last Mile (Receivable)'
                    ],
                ];

                // ============================================
                // Prepare File
                // ============================================
                $folder = now()->format('Y-m');
                $fileName = sprintf(
                    'cod-refund-%s-%s.xlsx',
                    $this->paymentDate,
                    now()->format('Ymd_His')
                );

                $directory = storage_path("app/private/finance-reports/{$folder}");

                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $filePath = "{$directory}/{$fileName}";

                // ======================================================
                // Spreadsheet Generate
                // ======================================================
                $spreadsheetStart = microtime(true);
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('COD Refund');

                // Header
                $headers = [
                    'Account',
                    'Partner',
                    'Analytic Account',
                    'Date',
                    'Due Date',
                    'Debit',
                    'Credit',
                    'Operating Unit',
                    'Label'
                ];

                $sheet->fromArray($headers, null, 'A1');
                $rowIndex = 2;

                foreach ($rows as $row) {
                    $colIndex = 1;

                    foreach ($row as $value) {
                        $coordinate = Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;

                        if ($colIndex == 1) {
                            $sheet->setCellValueExplicit(
                                $coordinate,
                                (string) $value,
                                DataType::TYPE_STRING
                            );
                        } else {
                            $sheet->setCellValue($coordinate, $value);
                        }
                        $colIndex++;
                    }

                    $rowIndex++;
                }

                // ======================================================
                // Fixed Width
                // ======================================================

                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(15);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(15);
                $sheet->getColumnDimension('H')->setWidth(18);
                $sheet->getColumnDimension('I')->setWidth(70);

                Log::info(
                    'Spreadsheet Build Time: ' .
                    round(microtime(true) - $spreadsheetStart, 3) .
                    ' s'
                );

                // ======================================================
                // Save File
                // ======================================================
                $saveStart = microtime(true);

                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);

                Log::info(
                    'Spreadsheet Save Time: ' .
                    round(microtime(true) - $saveStart, 3) .
                    ' s'
                );

                $spreadsheet->disconnectWorksheets();

                unset($writer, $spreadsheet);

                // ======================================================
                // DB Transaction (ONLY DB OPERATIONS)
                // ======================================================
                DB::transaction(function () use (
                    $fileName,
                    $folder,
                    $rows,
                    &$updatedRows,
                    $startTime
                ) {

                $paymentDate = $this->paymentDate;
                // ============================================
                // Save Export Record
                // ============================================
                $exportId = DB::table('finance_exports')
                    ->insertGetId([
                        'filename' => $fileName,
                        'filepath' => "private/finance-reports/{$folder}/{$fileName}",
                        'report_date' => $this->paymentDate,
                        'report_type' => 'cod-refund',
                        'category' => $this->category,
                        'filtered' => 'ALL',
                        'total_rows' => count($rows),
                        'duration' => round(
                            microtime(true) - $startTime,
                            2
                        ),
                        'exported_by' => $this->exportedBy,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                // ============================================
                // Update Upload Data Flag
                // ============================================
                $updatedRows = DB::table('upload_data')
                    ->whereDate('payment_date', $this->paymentDate)
                    ->where('refund', 1)
                    ->whereIn('vendor_type', ['Vendor', 'Invoice'])
                    ->where('cod_refund_export', 0)
                    ->update([
                        'cod_refund_export' => $exportId,
                        'updated_at' => now(),
                    ]);

                // ============================================
                // Action Log
                // ============================================
                DB::table('action_logs')->insert([
                    'action' => 'EXPORT',
                    'keywords' => 'COD_REFUND',
                    'user' => $this->exportedBy,
                    'log' =>
                        "COD Refund report generated successfully. " .
                        "Payment Date: {$this->paymentDate}, " .
                        "File Name: {$fileName}, " .
                        "Rows Updated: {$updatedRows}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ======================================================
                // Final Log (outside transaction)
                // ======================================================
                Log::info(
                    "COD Refund Export Completed | " .
                    "Payment Date: {$this->paymentDate} | " .
                    "File Name: {$fileName} | " .
                    "Rows: {$updatedRows} | " .
                    "Total Time: " . round(microtime(true) - $startTime, 2) . " sec"
                );
            });

        } catch (\Throwable $e) {

            Log::error(
                "COD Refund Job Failed | " .
                "Payment Date: {$this->paymentDate} | " .
                $e->getMessage(),
                [
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw $e;
        }
    }
}