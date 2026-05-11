<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

    public function handle()
    {
        $startTime = microtime(true);

        try {

            DB::transaction(function () use ($startTime) {

                // -----------------------------
                // BASE QUERY (LOCK rows)
                // -----------------------------
                $baseQuery = DB::table('upload_data')
                    ->whereDate('payment_date', $this->paymentDate)
                    ->where('refund', 1)
                    ->where('vendor_type', 'Vendor')
                    ->where('finance_export', 0)
                    ->lockForUpdate();

                // -----------------------------
                // GET IDS FIRST (important)
                // -----------------------------
                // Vendor IDs
                $vendorIds = (clone $baseQuery)->pluck('id');

                // Invoice IDs
                $invoiceIds = DB::table('upload_data')
                    ->whereDate('payment_date', $this->paymentDate)
                    ->where('refund', 1)
                    ->where('vendor_type', 'Invoice')
                    ->where('finance_export', 0)
                    ->pluck('id');

                // Merge All IDs
                $ids = $vendorIds
                    ->merge($invoiceIds)
                    ->unique()
                    ->values();

                if ($ids->isEmpty()) {
                    DB::table('action_logs')->insert([
                        'action'     => 'EXPORT',
                        'keywords'   => 'COD_REFUND',
                        'user'       => $this->exportedBy,
                        'log'        => "No data found for {$this->paymentDate}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info("No data found for COD refund export: {$this->paymentDate}");
                    return;
                }

                // -----------------------------
                // REUSE QUERY WITH IDS (faster + safe)
                // -----------------------------
                $query = DB::table('upload_data')->whereIn('id', $vendorIds);

                // -----------------------------
                // GROUP CALCULATIONS
                // -----------------------------
                $group1 = (clone $query)
                    ->where('service_type', 'express')
                    ->where(function ($q) {
                        $q->where('customer_reference_no', 'like', 'E%')
                          ->orWhere('customer_reference_no', 'like', 'PUB%')
                          ->orWhere('customer_reference_no', 'like', 'PJ%');
                    })
                    ->sum('cod_payable_amount');

                $group2 = (clone $query)
                    ->where('service_type', 'express')
                    ->where('customer_reference_no', 'like', 'PB%')
                    ->sum('cod_payable_amount');

                $group3 = (clone $query)
                    ->where('service_type', 'same_day_delivery')
                    ->sum('cod_payable_amount');

                $totalCredit = $group1 + $group2 + $group3;

                // -------------------------------------------------
                // PAYMENT ACCOUNT INVOICE
                // -------------------------------------------------
                $invoiceQuery = DB::table('upload_data')
                    ->whereDate('payment_date', $this->paymentDate)
                    ->where('refund', 1)
                    ->where('vendor_type', 'Invoice')
                    ->where('finance_export', 0);

                // E,PUB,PJ
                $invoiceGroup1 = (clone $invoiceQuery)
                    ->where('service_type', 'express')
                    ->where(function ($q) {
                        $q->where('customer_reference_no', 'like', 'E%')
                        ->orWhere('customer_reference_no', 'like', 'PUB%')
                        ->orWhere('customer_reference_no', 'like', 'PJ%');
                    })
                    ->sum('cod_payable_amount');

                // BP
                $invoiceGroup2 = (clone $invoiceQuery)
                    ->where('service_type', 'express')
                    ->where('customer_reference_no', 'like', 'PB%')
                    ->sum('cod_payable_amount');

                // Same Day
                $invoiceGroup3 = (clone $invoiceQuery)
                    ->where('service_type', 'same_day_delivery')
                    ->sum('cod_payable_amount');

                $invoiceTotalCredit = $invoiceGroup1  + $invoiceGroup2 + $invoiceGroup3;

                // -----------------------------
                // BUILD CSV
                // -----------------------------
                $rows = [];
                $rows[] = ['Type', 'Vendor', '', '', ''];

                // =================================================
                // 1. PAYMENT ACCOUNT VENDOR
                // =================================================
                $rows[] = [
                    'E/PUB/PJ',
                    '231604',
                    'CA-Cash In Hand E Code Interim',
                    '',
                    number_format((float)$group1, 2, '.', '')
                ];

                $rows[] = [
                    'PB',
                    '231600',
                    'CA-Cash In Hand PBAZ Interim',
                    '',
                    number_format((float)$group2, 2, '.', '')
                ];

                $rows[] = [
                    'Same Day',
                    '231606',
                    'CA-Cash In Hand Same Day Interim A/C',
                    '',
                    number_format((float)$group3, 2, '.', '')
                ];

                $rows[] = [
                    '',
                    '355003',
                    'CL-Payable - Last Mile (New)',
                    number_format((float)$totalCredit, 2, '.', ''),
                    ''
                ];

                // Empty Row
                $rows[] = ['', '', '', '', ''];

                // =================================================
                // 2. PAYMENT ACCOUNT INVOICE
                // =================================================

                $rows[] = [
                    'Type',
                    'Invoice',
                    '',
                    '',
                    ''
                ];

                // E,PUB,PJ
                $rows[] = [
                    'E,PUB,PJ',
                    '231604',
                    'CA-Cash in Hand E Code Interim',
                    number_format((float)$invoiceGroup1, 2, '.', ''),
                    ''
                ];

                // BP
                $rows[] = [
                    'BP',
                    '231600',
                    'CA-Cash in Hand BPAZ Interim',
                    number_format((float)$invoiceGroup2, 2, '.', ''),
                    ''
                ];

                // Same Day
                $rows[] = [
                    'Same Day',
                    '231606',
                    'CA-Cash in Hand Same Day Interim A/C',
                    number_format((float)$invoiceGroup3, 2, '.', ''),
                    ''
                ];

                // Credit
                $rows[] = [
                    '',
                    '272750',
                    'CA-Last Mile (Receivable)',
                    '',
                    number_format((float)$invoiceTotalCredit, 2, '.', '')
                ];

                // -----------------------------
                // FILE CREATE
                // -----------------------------
                $folder = now()->format('Y-m');
                $fileName = "cod-refund-{$this->paymentDate}-" . now()->format('His') . ".csv";

                $directory = storage_path("app/private/finance-reports/{$folder}");
                if (!is_dir($directory)) mkdir($directory, 0755, true);

                $filePath = "{$directory}/{$fileName}";
                $handle = fopen($filePath, 'w');

                fputcsv($handle, [
                    '',
                    '',
                    '',
                    '',
                    $this->paymentDate
                ]);

                fputcsv($handle, [
                    'Customer',
                    'Payable Account',
                    'COA Name',
                    'Debit',
                    'Credit'
                ]);

                foreach ($rows as $r) {
                    fputcsv($handle, $r);
                }

                fclose($handle);

                // -----------------------------
                // INSERT EXPORT + GET ID
                // -----------------------------
                $exportId = DB::table('finance_exports')->insertGetId([
                    'filename' => $fileName,
                    'filepath' => "private/finance-reports/{$folder}/{$fileName}",
                    'report_date' => $this->paymentDate,
                    'report_type' => 'cod-refund',
                    'category' => $this->category,
                    'filtered' => 'ALL',
                    'total_rows' => count($rows),
                    'duration' => round(microtime(true) - $startTime, 2),
                    'exported_by' => $this->exportedBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // -----------------------------
                // UPDATE ONLY SELECTED ROWS
                // -----------------------------
                DB::table('upload_data')
                    ->whereIn('id', $ids)
                    ->update([
                        'finance_export' => $exportId,
                        'updated_at' => now(),
                    ]);

                // saved user action logs
                DB::table('action_logs')->insert([
                    'action'     => 'EXPORT',
                    'keywords'   => 'COD_REFUND',
                    'user'       => $this->exportedBy,
                    'log' => "COD Refund report generated successfully. " ."Payment Date: {$this->paymentDate}, " ."Exported File Name: {$fileName}, " ."Rows Count: " . count($ids),'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info(
                    "COD Refund Report Generated | " .
                    "Payment Date: {$this->paymentDate} | " .
                    "Exported File Name: {$fileName} | " .
                    "Row Count: " . count($ids)
                );

            });

        } catch (\Throwable $e) {
            Log::error("COD Refund job failed: " . $e->getMessage());
            throw $e;
        }
    }
}