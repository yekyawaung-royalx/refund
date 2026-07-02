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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class GenerateFinanceSenderReceiverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $accountingDate;
    protected string $category;
    protected string $exportedBy;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(
        string $accountingDate,
        string $category = 'all',
        string $exportedBy = 'system'
    ) {
        $this->accountingDate = $accountingDate;
        $this->category = $category;
        $this->exportedBy = $exportedBy;
    }

    public function handle()
    {
        $startTime = microtime(true);

        try {

            $rows = [];
            $ids = collect();
            // Header
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

            // =========================================================
            // BASE QUERY
            // =========================================================
            $base = DB::table('upload_data as u')
                ->whereDate('u.accounting_date', $this->accountingDate)
                ->where('u.sender_receiver_export', 0);

            // =========================================================
            // 1. SENDER PAY PREPAID
            // =========================================================
            if ($this->category === 'all' || $this->category === 'sender-pay-prepaid') {

                // -------------------------
                // IDs (FOR UPDATE ONLY)
                // -------------------------
                $senderIds = (clone $base)
                    ->where('u.payment_by', 'Sender Pay')
                    ->where('u.payment_type', 'Prepaid')
                    ->pluck('u.id');

                // -------------------------
                // DATA (FOR EXPORT ONLY)
                // -------------------------
                $senderData = DB::table('upload_data as u')
                    ->join('analytics as a', 'u.origin_branch', '=', 'a.reference')
                    ->whereDate('u.accounting_date', $this->accountingDate)
                    ->where('u.sender_receiver_export', 0)
                    ->where('u.payment_by', 'Sender Pay')
                    ->where('u.payment_type', 'Prepaid')
                    ->whereNotNull('a.journal')
                    ->select(
                        'u.origin_branch as branch',
                        'a.journal',
                        DB::raw('SUM(u.express_income_amount) as amount')
                    )
                    ->groupBy('u.origin_branch', 'a.journal')
                    ->get();

                $rows[] = $headerRow;
                
                foreach ($senderData as $s) {

                    $rows[] = [
                        '506010',
                        '',
                        $s->branch,
                        $this->accountingDate,
                        $this->accountingDate,
                        '0.00',
                        number_format((float)$s->amount, 2, '.', ''),
                        'OPR',
                        $this->accountingDate.' SPPP '.$s->branch.' Express Income Amount'
                    ];

                    $rows[] = [
                        $s->journal,
                        '',
                        $s->branch,
                        $this->accountingDate,
                        $this->accountingDate,
                        number_format((float)$s->amount, 2, '.', ''),
                        '0.00',
                        'OPR',
                        $this->accountingDate.' SPPP '.$s->branch.' Branch Cash'
                    ];
                }
                

                // FIXED pluck
                //$ids = $ids->merge($sender->pluck('id'));
                $ids = $ids->merge($senderIds);
            }

            // =========================================================
            // 2. RECEIVER PAY POSTPAID
            // =========================================================
            if ($this->category === 'all' || $this->category === 'receiver-pay-postpaid') {

                // -------------------------
                // IDs (FOR UPDATE ONLY)
                // -------------------------
                $receiverIds = (clone $base)
                    ->where('u.payment_by', 'Receiver Pay')
                    ->where('u.payment_type', 'Postpaid')
                    ->pluck('u.id');

                // -------------------------
                // DATA (FOR EXPORT ONLY)
                // -------------------------
                $receiverData = DB::table('upload_data as u')
                    ->join('analytics as a', 'u.destination_branch', '=', 'a.reference')
                    ->whereDate('u.accounting_date', $this->accountingDate)
                    ->where('u.sender_receiver_export', 0)
                    ->where('u.payment_by', 'Receiver Pay')
                    ->where('u.payment_type', 'Postpaid')
                    ->whereNotNull('a.journal')
                    ->select(
                        'u.destination_branch as branch',
                        'a.journal',
                        DB::raw('SUM(u.express_income_amount) as amount')
                    )
                    ->groupBy('u.destination_branch', 'a.journal')
                    ->get();

                foreach ($receiverData as $r) {

                    $rows[] = [
                        '506010',
                        '',
                        $r->branch,
                        $this->accountingDate,
                        $this->accountingDate,
                        '0.00',
                        number_format((float)$r->amount, 2, '.', ''),
                        'OPR',
                        $this->accountingDate.' RPPP '.$r->branch.' Express Income Amount'
                    ];

                    $rows[] = [
                        $r->journal,
                        '',
                        $r->branch,
                        $this->accountingDate,
                        $this->accountingDate,
                        number_format((float)$r->amount, 2, '.', ''),
                        '0.00',
                        'OPR',
                        $this->accountingDate.' RPPP '.$r->branch.' Branch Cash'
                    ];

                }
               
                // FIXED pluck
                //$ids = $ids->merge($receiver->pluck('id'));
                $ids = $ids->merge($receiverIds);
            }

            $ids = $ids->unique()->values();

            // =========================================================
            // FILE CREATE (XLSX)
            // =========================================================
            $folder = now()->format('Y-m');
            $fileName = "finance-sender-receiver-{$this->accountingDate}-" . now()->format('His') . ".xlsx";

            $directory = storage_path("app/private/finance-reports/{$folder}");

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $filePath = "{$directory}/{$fileName}";
            $relativePath = "private/finance-reports/{$folder}/{$fileName}";

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Sender Receiver');

            $rowIndex = 1;
            foreach ($rows as $row) {
                
                $sheet->setCellValueExplicit(
                    "A{$rowIndex}",
                    (string) $row[0],
                    DataType::TYPE_STRING
                );

                $sheet->fromArray(
                    array_slice($row, 1),
                    null,
                    "B{$rowIndex}"
                );

                $rowIndex++;
            }

            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Optional
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:I1');

            // =========================================================
            // SAVE XLSX
            // =========================================================
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);


            // =========================================================
            // EXPORT RECORD
            // =========================================================
            $exportId = DB::table('finance_exports')->insertGetId([
                'filename' => $fileName,
                'filepath' => $relativePath,
                'report_date' => $this->accountingDate,
                'report_type' => 'sender-receiver',
                'category' => $this->category,
                'filtered' => $this->category ?? 'ALL',
                'total_rows' => count($rows),
                'duration' => round(microtime(true) - $startTime, 2),
                'exported_by' => $this->exportedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // =========================================================
            // UPDATE upload_data
            // =========================================================
            if ($ids->isNotEmpty()) {
                // $ids->chunk(1000)->each(function ($chunk) use ($exportId) {
                //     DB::table('upload_data')
                //         ->whereIn('id', $chunk)
                //         ->update([
                //             'sender_receiver_export' => $exportId,
                //             'updated_at' => now(),
                //         ]);
                // });
            }

            DB::table('action_logs')->insert([
                'action' => 'EXPORT',
                'keywords' => 'FINANCE_SENDER_RECEIVER',
                'user' => $this->exportedBy,
                'log' => "Finance export completed ({$this->category}) | {$this->accountingDate} | ID: {$exportId}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Throwable $e) {

            Log::error("Finance Export Failed: " . $e->getMessage());
            throw $e;
        }
    }
}