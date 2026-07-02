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

            DB::transaction(function () use ($startTime) {

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

                // ============================================
                // Vendor Query
                // ============================================
                $vendorQuery = DB::table('upload_data')
                    ->whereDate('payment_date', $this->paymentDate)
                    ->where('refund', 1)
                    ->where('vendor_type', 'Vendor')
                    ->where('cod_refund_export', 0);

                // ============================================
                // Vendor Groups
                // ============================================
                $group1 = (clone $vendorQuery)
                    ->where('service_type', 'express')
                    ->where(function ($q) {
                        $q->where('customer_reference_no', 'like', 'E%')
                            ->orWhere('customer_reference_no', 'like', 'PUB%')
                            ->orWhere('customer_reference_no', 'like', 'PJ%')
                            ->orWhere('customer_reference_no', 'like', 'PT%');
                    })
                    ->sum('cod_payable_amount');

                $group2 = (clone $vendorQuery)
                    ->where('service_type', 'express')
                    ->where('customer_reference_no', 'like', 'BP%')
                    ->sum('cod_payable_amount');

                $group3 = (clone $vendorQuery)
                    ->where('service_type', 'same_day_delivery')
                    ->sum('cod_payable_amount');

                $totalCredit = $group1 + $group2 + $group3;

                // ============================================
                // Invoice Query
                // ============================================
                $invoiceQuery = DB::table('upload_data')
                    ->whereDate('payment_date', $this->paymentDate)
                    ->where('refund', 1)
                    ->where('vendor_type', 'Invoice')
                    ->where('cod_refund_export', 0);

                // ============================================
                // Invoice Groups
                // ============================================
                $invoiceGroup1 = (clone $invoiceQuery)
                    ->where('service_type', 'express')
                    ->where(function ($q) {
                        $q->where('customer_reference_no', 'like', 'E%')
                            ->orWhere('customer_reference_no', 'like', 'PUB%')
                            ->orWhere('customer_reference_no', 'like', 'PJ%')
                            ->orWhere('customer_reference_no', 'like', 'PT%');
                    })
                    ->sum('cod_payable_amount');

                $invoiceGroup2 = (clone $invoiceQuery)
                    ->where('service_type', 'express')
                    ->where('customer_reference_no', 'like', 'BP%')
                    ->sum('cod_payable_amount');

                $invoiceGroup3 = (clone $invoiceQuery)
                    ->where('service_type', 'same_day_delivery')
                    ->sum('cod_payable_amount');

                $invoiceTotalCredit =
                    $invoiceGroup1 +
                    $invoiceGroup2 +
                    $invoiceGroup3;

                $paymentDate = $this->paymentDate;

                // ============================================
                // Build CSV Rows
                // ============================================
                $rows = [
                    [
                        '231604',
                        '',
                        'YGN',
                        $paymentDate,
                        $paymentDate,
                        '0.00',
                        number_format((float) $group1, 2, '.', ''),
                        'OPR',
                        $paymentDate.' Vendor Refund E/PUB/PJ/PT CA-Cash In Hand E Code Interim'
                    ],

                    [
                        '231600',
                        '',
                        'YGN',
                        $paymentDate,
                        $paymentDate,
                        '0.00',
                        number_format((float) $group2, 2, '.', ''),
                        'OPR',
                        $paymentDate.'Vendor Refund BP CA-Cash In Hand BPAZ Interim'
                    ],

                    [
                        '231606',
                        '',
                        'YGN',
                        $paymentDate,
                        $paymentDate,
                        '0.00',
                        number_format((float) $group3, 2, '.', ''),
                        'OPR',
                        $paymentDate.' Vendor Refund Same Day CA-Cash In Hand Same Day Interim A/C'
                    ],

                    [
                        '355003',
                        '',
                        'YGN',
                        $paymentDate,
                        $paymentDate,
                        number_format((float) $totalCredit, 2, '.', ''),
                        '0.00',
                        'OPR',
                        $paymentDate.' Vendor Refund CL-Payable - Last Mile (New)'
                    ],

                    [
                        '231604',
                        '',
                        'YGN',
                        $paymentDate,
                        $paymentDate,
                        '0.00',
                        number_format(abs((float) $invoiceGroup1), 2, '.', ''),
                        'OPR',
                        $paymentDate.' Vendor Invoice E/PUB/PJ/PT CA-Cash In Hand E Code Interim'
                    ],

                    [
                        '231600',
                        '',
                        'YGN',
                        $paymentDate,
                        $paymentDate,
                        '0.00',
                        number_format(abs((float) $invoiceGroup2), 2, '.', ''),
                        'OPR',
                        $paymentDate.' Vendor Invoice BP CA-Cash In Hand BPAZ Interim'
                    ],

                    [
                        '231606',
                        '',
                        'YGN',
                        $paymentDate,
                        $paymentDate,
                        '0.00',
                        number_format(abs((float) $invoiceGroup3), 2, '.', ''),
                        'OPR',
                        $paymentDate.' Vendor Invoice Same Day CA-Cash In Hand Same Day Interim A/C'
                    ],

                    [
                        '272750',
                        '',
                        'YGN',
                        $paymentDate,
                        $paymentDate,
                        number_format(abs((float) $invoiceTotalCredit), 2, '.', ''),
                        '0.00',
                        'OPR',
                        $paymentDate.' Vendor Refund CL-Payable - Last Mile (Receivable)'
                    ],
                ];

                // ============================================
                // Generate File
                // ============================================
                $folder = now()->format('Y-m');
                $fileName = "cod-refund-{$this->paymentDate}-" .
                        now()->format('Ymd_His') .
                        ".xlsx";

                $directory = storage_path("app/private/finance-reports/{$folder}");

                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $filePath = "{$directory}/{$fileName}";

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

                $sheet->fromArray(
                    $headers,
                    null,
                    'A1'
                );

                // Data Rows
                $sheet->fromArray(
                    $rows,
                    null,
                    'A2'
                );


                // Auto width
                foreach (range('A','I') as $column) {
                    $sheet
                        ->getColumnDimension($column)
                        ->setAutoSize(true);
                }

                // Header style
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                    ],
                ]);

                // Save xlsx
                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

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
                // Update Export Flag
                // ============================================
                // $updatedRows = DB::table('upload_data')
                //     ->whereDate('payment_date', $this->paymentDate)
                //     ->where('refund', 1)
                //     ->whereIn('vendor_type', ['Vendor', 'Invoice'])
                //     ->where('cod_refund_export', 0)
                //     ->update([
                //         'cod_refund_export' => $exportId,
                //         'updated_at' => now(),
                //     ]);
                $updatedRows = count($rows);

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
                        "Exported File Name: {$fileName}, " .
                        "Rows Count: {$updatedRows}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info(
                    "COD Refund Report Generated | " .
                    "Payment Date: {$this->paymentDate} | " .
                    "Exported File Name: {$fileName} | " .
                    "Updated Rows: {$updatedRows}"
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