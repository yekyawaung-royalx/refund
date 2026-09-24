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
        $startTime  = microtime(true);
        $timings    = [];
        /*
        |--------------------------------------------------------------------------
        | QUERY EXECUTION LOGGER
        |--------------------------------------------------------------------------
        */
        Log::info('[FinanceExport] START', [
            'date' => $this->accountingDate,
            'category' => $this->category,
            'exported_by' => $this->exportedBy,
        ]);


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

                $queryStart = microtime(true);
                // -------------------------
                // IDs (FOR UPDATE ONLY)
                // -------------------------
                $senderIds = (clone $base)
                    ->where('u.payment_by', 'Sender Pay')
                    ->where('u.payment_type', 'Prepaid')
                    ->pluck('u.id');

                $timings['sender_ids_ms'] = round(
                    (microtime(true) - $queryStart) * 1000,
                    2
                );

                $timings['sender_ids_count'] = $senderIds->count();

                // -------------------------
                // DATA (FOR EXPORT ONLY)
                // -------------------------
                $queryStart = microtime(true);
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
                        'u.service_type',
                        DB::raw('SUM(u.express_income_amount) as amount')
                    )
                    ->groupBy(
                        'u.origin_branch', 
                        'a.journal',
                        'u.service_type'
                    )
                    ->get();

                $timings['sender_data_ms'] = round(
                    (microtime(true) - $queryStart) * 1000,
                    2
                );
                $timings['sender_data_rows'] = $senderData->count();

                $rows[] = $headerRow;
                
                $senderTotal = 0;

                foreach ($senderData as $s) {
                    $account = $s->service_type === 'priority'
                        ? '506015'
                        : '506010';

                    $amount = (float) $s->amount;

                    $senderTotal += $amount;

                    // Credit
                    $rows[] = [
                        $account,
                        '',
                        $s->branch,
                        $this->accountingDate,
                        $this->accountingDate,
                        '0.00',
                        number_format($amount, 2, '.', ''),
                        'OPR',
                        $this->accountingDate
                            . ' SPPP '
                            . $s->branch
                            . ' '
                            . ucfirst($s->service_type)
                            . ' Income Amount'
                    ];
                }

                // One Debit row
                if ($senderTotal > 0) {
                    $rows[] = [
                        $senderData->first()->journal,
                        '',
                        $senderData->first()->branch,
                        $this->accountingDate,
                        $this->accountingDate,
                        number_format($senderTotal, 2, '.', ''),
                        '0.00',
                        'OPR',
                        $this->accountingDate
                            . ' SPPP '
                            . $senderData->first()->branch
                            . ' Branch Cash'
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
                $queryStart = microtime(true);
                $receiverIds = (clone $base)
                    ->where('u.payment_by', 'Receiver Pay')
                    ->where('u.payment_type', 'Postpaid')
                    ->pluck('u.id');

                $timings['receiver_ids_ms'] = round(
                    (microtime(true) - $queryStart) * 1000,
                    2
                );
                $timings['receiver_ids_count'] = $receiverIds->count();

                // -------------------------
                // DATA (FOR EXPORT ONLY)
                // -------------------------
                $queryStart = microtime(true);
                $receiverData = DB::table('upload_data as u')
                    ->join('analytics as a', 'u.destination_branch', '=', 'a.reference')
                    ->whereDate('u.accounting_date', $this->accountingDate)
                    ->where('u.sender_receiver_export', 0)
                    ->where('u.payment_by', 'Receiver Pay')
                    ->where('u.payment_type', 'Postpaid')
                    ->whereNotNull('a.journal')
                    ->whereIn('u.service_type', ['express', 'priority'])
                    ->select(
                        'u.destination_branch as branch',
                        'a.journal',
                        'u.service_type',
                        DB::raw('SUM(u.express_income_amount) as amount')
                    )
                    ->groupBy(
                        'u.destination_branch', 
                        'a.journal',
                        'u.service_type'
                    )
                    ->get();

                $timings['receiver_data_ms'] = round(
                    (microtime(true) - $queryStart) * 1000,
                    2
                );
                $timings['receiver_data_rows'] = $receiverData->count();

                $receiverTotal = 0;

                foreach ($receiverData as $r) {
                    $account = $r->service_type === 'priority'
                        ? '506015'
                        : '506010';

                    $amount = (float) $r->amount;

                    // Total for Debit row
                    $receiverTotal += $amount;

                    // Credit row
                    $rows[] = [
                        $account,
                        '',
                        $r->branch,
                        $this->accountingDate,
                        $this->accountingDate,
                        '0.00',
                        number_format($amount, 2, '.', ''),
                        'OPR',
                        $this->accountingDate
                            . ' RPPP '
                            . $r->branch
                            . ' '
                            . ucfirst($r->service_type)
                            . ' Income Amount'
                    ];
                }

                // One Debit row only
                if ($receiverTotal > 0) {
                    $rows[] = [
                        $receiverData->first()->journal,
                        '',
                        $receiverData->first()->branch,
                        $this->accountingDate,
                        $this->accountingDate,
                        number_format($receiverTotal, 2, '.', ''),
                        '0.00',
                        'OPR',
                        $this->accountingDate
                            . ' RPPP '
                            . $receiverData->first()->branch
                            . ' Branch Cash'
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
            $xlsxStart = microtime(true);
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

            $timings['xlsx_build_ms'] = round(
                (microtime(true) - $xlsxStart) * 1000,
                2
            );
            $timings['xlsx_rows'] = count($rows);

            // =========================================================
            // SAVE XLSX
            // =========================================================
            $saveStart = microtime(true);
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            $timings['xlsx_save_ms'] = round(
                (microtime(true) - $saveStart) * 1000,
                2
            );

            $timings['xlsx_size_mb'] = round(
                filesize($filePath) / 1024 / 1024,
                2
            );

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);


            // =========================================================
            // EXPORT RECORD
            // =========================================================
            $exportDbStart = microtime(true); /*<-- start log -->*/
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

            /*<-- end log -->*/
            $timings['export_db_ms'] = round(
                (microtime(true) - $exportDbStart) * 1000,
                2
            );
            $timings['export_id'] = $exportId;

            // =========================================================
            // UPDATE upload_data
            // =========================================================
            $actionLogStart = microtime(true);
            if ($ids->isNotEmpty()) {
                $ids->chunk(1000)->each(function ($chunk) use ($exportId) {
                    DB::table('upload_data')
                        ->whereIn('id', $chunk)
                        ->update([
                            'sender_receiver_export' => $exportId,
                            'updated_at' => now(),
                        ]);
                });
            }

            DB::table('action_logs')->insert([
                'action' => 'EXPORT',
                'keywords' => 'FINANCE_SENDER_RECEIVER',
                'user' => $this->exportedBy,
                'log' => "Finance export completed ({$this->category}) | {$this->accountingDate} | ID: {$exportId}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $timings['action_log_ms'] = round(
                (microtime(true) - $actionLogStart) * 1000,
                2
            );

            // =========================================================
            // TOTAL
            // =========================================================
            $timings['total_ms'] = round(
                (microtime(true) - $startTime) * 1000,
                2
            );

            $timings['total_seconds'] = round(
                microtime(true) - $startTime,
                2
            );

            $timings['total_rows'] = count($rows);
            $timings['total_ids'] = $ids->count();

            Log::info('[FinanceExport] COMPLETED', $timings);

        } catch (\Throwable $e) {

            Log::error("Finance Export Failed: " . $e->getMessage());
            throw $e;
        }
    }
}