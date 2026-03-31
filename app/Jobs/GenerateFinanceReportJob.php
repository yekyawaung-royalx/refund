<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateFinanceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $deliveredDate;
    protected ?string $branch;
    protected ?string $category; // all,cod-payable,cod-not-collect,cod-to-collect,cod-zero
    protected string $exportedBy;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(string $deliveredDate, ?string $branch = null, ?string $category = 'all', string $exportedBy)
    {
        $this->deliveredDate = $deliveredDate;
        $this->branch        = $branch;
        $this->category      = $category ?? 'all';
        $this->exportedBy    = $exportedBy;
    }

    public function handle()
    {
        $startTime = microtime(true);

        try {

            // -----------------------------
            // Category Conditions
            // -----------------------------
            $categories = [
                // COD Payable Amout + only)
                'cod-payable' => function ($q) {
                    $q->where('u.cod_payable_amount', '>', 0);
                },
                // COD Payable Amout -, COD Total Amount == 0
                'cod-not-collect' => function ($q) {
                    $q->where('u.cod_total_amount', 0)
                      ->where('u.cod_payable_amount', '<', 0);
                },
                // COD Payable Amout -, COD Total Amount +
                'cod-to-collect' => function ($q) {
                    $q->where('u.cod_total_amount', '>', 0)
                      ->where('u.cod_payable_amount', '<', 0);
                },
                // COD Payable Amout == 0, COD Total Amount
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

            // -----------------------------
            // Selected categories
            // -----------------------------
            $selectedCategories = $this->category === 'all'
                ? $categories
                : [$this->category => $categories[$this->category] ?? null];

            $insertData = [];
            $now = now();

            // -----------------------------
            // Loop categories
            // -----------------------------
            foreach ($selectedCategories as $categoryName => $condition) {

                if (!$condition) continue;
                $journalCode = $journalMap[$categoryName] ?? 'CODPL';

                $query = DB::table('upload_data as u')
                    ->join('analytics as a', 'u.destination_branch', '=', 'a.reference')
                    ->whereDate('u.delivered_date', $this->deliveredDate)
                    ->whereNotNull('a.journal');

                if ($this->branch && $this->branch !== 'ALL') {
                    $query->where('u.destination_branch', $this->branch);
                }

                // Apply condition
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

                foreach ($results as $row) {

                    // 4 rows per category
                    $insertData[] = [
                        'journal' => $journalCode,
                        'delivered_date' => $this->deliveredDate,
                        'ref' => null,
                        'analytic' => $row->reference,
                        'account' => '506032',
                        'label' => 'COD Express Income Amount',
                        'operation_unit' => 'OPR',
                        'debit' => 0,
                        'credit' => $row->cod_express_income ?? 0,
                        'category' => $categoryName,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $insertData[] = [
                        'journal' => $journalCode,
                        'delivered_date' => $this->deliveredDate,
                        'ref' => null,
                        'analytic' => $row->reference,
                        'account' => '506030',
                        'label' => 'COD Income Amount',
                        'operation_unit' => 'OPR',
                        'debit' => 0,
                        'credit' => $row->cod_income ?? 0,
                        'category' => $categoryName,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $insertData[] = [
                        'journal' => $journalCode,
                        'delivered_date' => $this->deliveredDate,
                        'ref' => null,
                        'analytic' => $row->reference,
                        'account' => '355003',
                        'label' => 'COD Payable Amount',
                        'operation_unit' => 'OPR',
                        'debit' => 0,
                        'credit' => $row->cod_payable ?? 0,
                        'category' => $categoryName,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $insertData[] = [
                        'journal' => $journalCode,
                        'delivered_date' => $this->deliveredDate,
                        'ref' => null,
                        'analytic' => $row->reference,
                        'account' => 'Branch Cash ('.$row->reference.')',
                        'label' => 'COD Total',
                        'operation_unit' => 'OPR',
                        'debit' => $row->cod_total ?? 0,
                        'credit' => 0,
                        'category' => $categoryName,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (empty($insertData)) {
                Log::info("No data found for {$this->deliveredDate}");
                return;
            }

            // -----------------------------
            // CSV Export
            // -----------------------------
            $folder = now()->format('Y-m');
            $timestamp = now()->format('Ymd_His');
            $fileName = "finance-report-{$this->deliveredDate}-{$timestamp}.csv";
            $relativePath = "private/finance-reports/{$folder}/{$fileName}";

            $directory = storage_path("app/private/finance-reports/{$folder}");
            if (!is_dir($directory)) mkdir($directory, 0755, true);

            $filePath = "{$directory}/{$fileName}";
            $handle = fopen($filePath, 'w');

            fputcsv($handle, [
                'journal',
                'delivered_date',
                'ref',
                'analytic',
                'account',
                'label',
                'operation_unit',
                'debit',
                'credit',
                'category',
                'created_at'
            ]);

            foreach ($insertData as $row) {
                fputcsv($handle, [
                    $row['journal'],
                    $row['delivered_date'],
                    $row['ref'],
                    $row['analytic'],
                    $row['account'],
                    $row['label'],
                    $row['operation_unit'],
                    $row['debit'],
                    $row['credit'],
                    $row['category'],
                    $row['created_at'],
                ]);
            }

            fclose($handle);

            // -----------------------------
            // Save export record
            // -----------------------------
            DB::table('finance_exports')->insert([
                'filename' => $fileName,
                'filepath' => $relativePath,
                'report_date' => $this->deliveredDate,
                'category' => $this->category,
                'filtered' => $this->branch ?? 'ALL',
                'total_rows' => count($insertData),
                'duration' => round((microtime(true) - $startTime), 2),
                'exported_by' => $this->exportedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Finance report generated for {$this->deliveredDate}");

        } catch (\Throwable $e) {
            Log::error("Finance report job failed: " . $e->getMessage());
            throw $e;
        }
    }
}