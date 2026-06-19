<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateFinanceBranchBankDepositJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected string $accountingDate;
    protected ?string $branch;
    protected ?string $category; // all,cod-payable,cod-not-collect,cod-to-collect,cod-zero
    protected string $exportedBy;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(string $accountingDate, ?string $branch = null, ?string $category = 'all', string $exportedBy)
    {
        $this->accountingDate = $accountingDate;
        $this->branch        = $branch;
        $this->category      = $category ?? 'all';
        $this->exportedBy    = $exportedBy;
    }

    public function handle()
    {
        $startTime = microtime(true);

        try {
            $categories = [
                'cod-payable' => function ($q) {
                    $q->where('u.cod_payable_amount', '>', 0);
                },
                'cod-not-collect' => function ($q) {
                    $q->where('u.cod_total_amount', 0)
                      ->where('u.cod_payable_amount', '<', 0);
                },
                'cod-to-collect' => function ($q) {
                    $q->where('u.cod_total_amount', '>', 0)
                      ->where('u.cod_payable_amount', '<', 0);
                },
                'cod-zero' => function ($q) {
                    $q->where('u.cod_payable_amount', 0);
                },
            ];

            $journalMap = [
                'cod-payable'      => 'CODPL',
                'cod-not-collect'  => 'CODRN',
                'cod-to-collect'   => 'CODRT',
                'cod-zero'         => 'CODZR',
            ];

            $selectedCategories = $this->category === 'all'
                ? $categories
                : [$this->category => $categories[$this->category] ?? null];

            $insertData = [];
            $now = now();

            foreach ($selectedCategories as $categoryName => $condition) {
                if (!$condition) continue;

                $journalCode = $journalMap[$categoryName] ?? 'CODPL';

                $query = DB::table('upload_data as u')
                    ->join('analytics as a', 'u.destination_branch', '=', 'a.reference')
                    ->whereDate('u.accounting_date', $this->accountingDate)
                    ->whereNotNull('a.journal')
                    ->where('u.branch_bank_deposit_export', 0);

                if ($this->branch && $this->branch !== 'ALL') {
                    $query->where('u.destination_branch', $this->branch);
                }

                $condition($query);

                $results = $query
                    ->groupBy('a.reference', 'a.journal')
                    ->select(
                        'a.reference',
                        'a.journal',
                        DB::raw('SUM(u.cod_express_income_amount) as cod_express_income'),
                        DB::raw('SUM(u.cod_income_amount) as cod_income'),
                        DB::raw('SUM(u.cod_payable_amount) as cod_payable'),
                        DB::raw('SUM(u.cod_total_amount) as cod_total')
                    )
                    ->get();

                // Category value insert at first row
                $isFirstRow = true;

                foreach ($results as $row) {
                    $journalValue   = $isFirstRow ? $journalCode : null;
                    $accountingDate = $isFirstRow ? $this->accountingDate : null;
                    $codPayable     = $row->cod_payable ?? 0;

                    // collect valid rows first
                    $rows = [];

                    //COD Express Income Amount
                    if ((float) $row->cod_express_income != 0) {
                        $rows[] = [
                            'journal'           => null,
                            'accounting_date'   => null,
                            'ref'               => null,
                            'analytic'          => $row->reference,
                            'account'           => '506030',
                            'label'             => 'COD Express Income Amount',
                            'operation_unit'    => 'OPR',
                            'debit'             => 0,
                            'credit'            => $row->cod_express_income ?? 0,
                            //'category'        => $categoryName,
                        ];
                    }

                    //COD Income Amount
                    if ((float) $row->cod_income != 0) {
                        $rows[] = [
                            'journal'           => null,
                            'accounting_date'   => null,
                            'ref'               => null,
                            'analytic'          => $row->reference,
                            'account'           => '506032',
                            'label'             => 'COD Income Amount',
                            'operation_unit'    => 'OPR',
                            'debit'             => 0,
                            'credit'            => $row->cod_income ?? 0,
                            //'category'        => $categoryName,
                        ];
                    }

                    //COD Payable Amount
                    if ((float) $codPayable != 0) {
                        $rows[] = [
                            'journal'           => null,
                            'accounting_date'   => null,
                            'ref'               => null,
                            'analytic'          => $row->reference,
                            'account'           => $codPayable >= 0 ? '355003' : '272750',
                            'label'             => 'COD Payable Amount',
                            'operation_unit'    => 'OPR',
                            'debit'             => $codPayable < 0 ? abs($codPayable) : 0,
                            'credit'            => $codPayable >= 0 ? $codPayable : 0,
                            //'category'        => $categoryName,
                        ];
                    }

                    //COD Total
                    if ((float) $row->cod_total != 0) {
                        $rows[] = [
                            'journal'           => null,
                            'accounting_date'   => null,
                            'ref'               => null,
                            'analytic'          => $row->reference,
                            'account'           => $row->journal,
                            'label'             => 'COD Total',
                            'operation_unit'    => 'OPR',
                            'debit'             => $row->cod_total ?? 0,
                            'credit'            => 0,
                            //'category'        => $categoryName,
                        ];
                    }

                    // first valid row gets journal & accounting date
                    if (!empty($rows)) {
                        $rows[0]['journal'] = $journalValue;
                        $rows[0]['accounting_date'] = $accountingDate;

                        $insertData = array_merge($insertData, $rows);
                    }

                    // next rows no journal
                    $isFirstRow = false;
                }
            }

            if (empty($insertData)) {
                // saved user action logs
                DB::table('action_logs')->insert([
                    'action'     => 'EXPORT',
                    'keywords'   => 'BRANCH_BANK_DEPOSIT',
                    'user'       => $this->exportedBy,
                    'log'        => "No data found for {$this->accountingDate}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info("No data found for {$this->accountingDate}");
                return;
            }

            // -----------------------------
            // CSV Export
            // -----------------------------
            $folder = now()->format('Y-m');
            $timestamp = now()->format('Ymd_His');
            $fileName = "branches-depoit-{$this->accountingDate}-{$timestamp}.csv";
            $relativePath = "private/finance-reports/{$folder}/{$fileName}";

            $directory = storage_path("app/private/finance-reports/{$folder}");
            if (!is_dir($directory)) mkdir($directory, 0755, true);

            $filePath = "{$directory}/{$fileName}";
            $handle = fopen($filePath, 'w');

            fputcsv($handle, [
                'journal',
                'accounting_date',
                'ref',
                'analytic',
                'account',
                'label',
                'operation_unit',
                'debit',
                'credit',
                //'category',
            ]);

            foreach ($insertData as $row) {
                fputcsv($handle, [
                    $row['journal'],
                    $row['accounting_date'],
                    $row['ref'],
                    $row['analytic'],
                    $row['account'],
                    $row['label'],
                    $row['operation_unit'],
                    $row['debit'],
                    $row['credit'],
                    //$row['category'],
                ]);
            }

            fclose($handle);

            // -----------------------------
            // Save export record & get id
            // -----------------------------
            Log::info("category: " . $this->category);
            $financeExportId = DB::table('finance_exports')->insertGetId([
                'filename' => $fileName,
                'filepath' => $relativePath,
                'report_date' => $this->accountingDate,
                'report_type' => 'branches-deposit',
                'category' => $this->category,
                'filtered' => $this->category ?? 'ALL',
                'total_rows' => count($insertData),
                'duration' => round((microtime(true) - $startTime), 2),
                'exported_by' => $this->exportedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // -----------------------------
            // Update upload_data.finance_export
            // -----------------------------
            foreach ($selectedCategories as $categoryName => $condition) {
                $query = DB::table('upload_data as u')
                    ->join('analytics as a', 'u.destination_branch', '=', 'a.reference')
                    ->whereDate('u.accounting_date', $this->accountingDate)
                    ->whereNotNull('a.journal');

                if ($this->branch && $this->branch !== 'ALL') {
                    $query->where('u.destination_branch', $this->branch);
                }

                $condition($query);

                $uploadIds = $query->pluck('u.id')->toArray();

                if (!empty($uploadIds)) {
                    DB::table('upload_data')
                        ->whereIn('id', $uploadIds)
                        ->update(['branch_bank_deposit_export' => $financeExportId]);
                }
            }

            // saved user action logs
            DB::table('action_logs')->insert([
                'action'     => 'EXPORT',
                'keywords'   => 'BRANCH_BANK_DEPOSIT',
                'user'       => $this->exportedBy,
                'log'        => "Branch Bank Deposit report generated. Accounting Date: {$this->accountingDate}, Exported File Name: {$fileName}, Row Count: " . count($insertData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info(
                "Branch Bank Deposit Report Generated | " .
                "Accounting Date: {$this->accountingDate} | " .
                "Exported File Name: {$fileName} | " .
                "Row Count: " . count($insertData)
            );
        } catch (\Throwable $e) {
            Log::error("Finance report job failed: " . $e->getMessage());
            throw $e;
        }
    }
}