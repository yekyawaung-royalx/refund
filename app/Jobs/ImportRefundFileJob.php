<?php

namespace App\Jobs;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportRefundFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected string $username;
    protected int $uploadId;
    protected string $filePath;

    public $timeout = 3600;
    public $tries = 3;
    protected int $batchSize = 1000;

    public function __construct(int $uploadId, string $filePath, string $username)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
        $this->username = $username;
    }

    public function handle()
    {
        $startTime = microtime(true);

        $upload = Upload::find($this->uploadId);
        if (!$upload) return;

        $upload->update([
            'status' => 'processing',
            'attempts' => $this->attempts(),
            'total_rows' => 0,
            'processed_rows' => 0,
            'failed_rows' => 0,
        ]);

        if (!file_exists($this->filePath)) {
            $upload->update([
                'status' => 'failed',
                'error_message' => 'File not found',
            ]);
            return;
        }

        $totalRows = 0;
        $processed = 0;
        $failed = 0;

        $failedInvalid = 0;
        $failedNotFound = 0;
        $failedAlreadyRefunded = 0;

        $failedRows = [];

        try {

            $file = new \SplFileObject($this->filePath);
            $file->setFlags(
                \SplFileObject::READ_CSV |
                \SplFileObject::SKIP_EMPTY |
                \SplFileObject::DROP_NEW_LINE
            );
            $file->setCsvControl(",");

            // -------- PASS 1: COUNT TOTAL ROWS --------
            $file->rewind();
            $file->fgetcsv(); // skip header
            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if ($row === false || (count($row) === 1 && $row[0] === null)) continue;
                $totalRows++;
            }
            $upload->update(['total_rows' => $totalRows]);

            // -------- PASS 2: PROCESS ROWS --------
            $file->rewind();
            $file->fgetcsv(); // skip header

            $batch = [];
            

            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if (!$row || $row[0] === null) continue;

                try {
                    $amountRaw = $row[0] ?? null;
                    $paymentDateRaw = $row[2] ?? null;
                    $waybillNo = $row[5] ?? null;
                    $vendorTypeRaw = $row[6] ?? null;

                    $vendorType = null;
                    
                    if ($vendorTypeRaw) {
                        $v = strtolower(trim($vendorTypeRaw));

                        if ($v === 'invoice') {
                            $vendorType = 'Invoice';
                        } elseif ($v === 'vendor') {
                            $vendorType = 'Vendor';
                        }
                    }

                    // ---------------- DATE NORMALIZE ----------------
                    $paymentDate = null;
                    if ($paymentDateRaw) {
                        $dt = \DateTime::createFromFormat('Y-m-d', $paymentDateRaw)
                              ?: \DateTime::createFromFormat('n/j/Y', $paymentDateRaw);
                        if ($dt) $paymentDate = $dt->format('Y-m-d');
                    }

                    // ---------------- AMOUNT CHECK ----------------
                    $amount = is_numeric($amountRaw) ? (float)$amountRaw : null;
                    if ($amount === 0) $amount = 0;

                    // ---------------- VALIDATION ----------------
                    if ($amount === null || !$paymentDate || !$waybillNo) {
                        $failed++;
                        $failedInvalid++;
                        $failedRows[] = [
                            'file_id' => $this->uploadId,
                            'waybill_no' => $waybillNo,
                            'reason' => 'invalid_data'
                        ];
                        continue;
                    }

                    $batch[] = [
                        'waybill_no'    => $waybillNo,
                        'payment_date'  => $paymentDate,
                        'vendor_type'   => $vendorType
                    ];

                    if (count($batch) >= $this->batchSize) {
                        $this->processBatch(
                            $batch,
                            $processed,
                            $failed,
                            $failedNotFound,
                            $failedAlreadyRefunded,
                            $failedRows
                        );
                        $batch = [];
                    }

                } catch (\Throwable $e) {
                    $failed++;
                    $failedInvalid++;
                    $failedRows[] = [
                        'file_id' => $this->uploadId,
                        'waybill_no' => $row[5] ?? null,
                        'reason' => 'exception'
                    ];
                }
            }

            // leftover batch
            if (!empty($batch)) {
                $this->processBatch(
                    $batch,
                    $processed,
                    $failed,
                    $failedNotFound,
                    $failedAlreadyRefunded,
                    $failedRows
                );
            }

            // -------- CSV EXPORT FOR FAILED ROWS --------
            $failedPath = null;
            if (!empty($failedRows)) {
                $folder = now()->format('Y-m');
                $directory = storage_path("app/private/refund-failed/{$folder}");
                if (!is_dir($directory)) mkdir($directory, 0755, true);

                $fileName = "failed_{$this->uploadId}_" . time() . ".csv";
                $fullPath = "{$directory}/{$fileName}";

                $handle = fopen($fullPath, 'w');
                fputcsv($handle, ['file_id', 'waybill_no', 'failed_reason']);
                foreach ($failedRows as $row) fputcsv($handle, [$row['file_id'], $row['waybill_no'], $row['reason']]);
                fclose($handle);

                $failedPath = "private/refund-failed/{$folder}/{$fileName}";
            }

            $duration = round(microtime(true) - $startTime, 2);
            $upload->update([
                'status' => 'completed',
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'processed_duration' => $duration,
                'failed_path' => $failedPath
            ]);

            // saved user action logs
            DB::table('action_logs')->insert([
                'action'     => 'IMPORT',
                'keywords'   => 'REFUND',
                'user'       => $this->username,
                'log'        => "Import completed successfully. Upload ID: {$this->uploadId}, Total Rows: {$totalRows}, Success: {$processed}, Failed: {$failed}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Refund import completed', [
                'upload_id' => $this->uploadId,
                'total_rows' => $totalRows,
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'invalid' => $failedInvalid,
                'not_found' => $failedNotFound,
                'already_refunded' => $failedAlreadyRefunded,
            ]);

        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            $upload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function processBatch(
        $rows,
        &$processed,
        &$failed,
        &$failedNotFound,
        &$failedAlreadyRefunded,
        &$failedRows
    ) {
        $waybills = array_column($rows, 'waybill_no');

        $existingRows = DB::table('upload_data')
            ->whereIn('waybill_no', $waybills)
            ->get()
            ->keyBy('waybill_no');

        foreach ($rows as $row) {
            $db = $existingRows[$row['waybill_no']] ?? null;

            if (!$db) {
                $failed++; $failedNotFound++;
                $failedRows[] = ['file_id'=>$this->uploadId,'waybill_no'=>$row['waybill_no'],'reason'=>'not_found'];
            } elseif ($db->refund == 1) {
                $failed++; $failedAlreadyRefunded++;
                $failedRows[] = ['file_id'=>$this->uploadId,'waybill_no'=>$row['waybill_no'],'reason'=>'already_refunded'];
            } else {
                $processed++;
            }
        }

        $validRows = array_filter($rows, fn($row) => isset($existingRows[$row['waybill_no']]) && $existingRows[$row['waybill_no']]->refund == 0);
        if (!empty($validRows)) $this->updateBatch($validRows);
    }

    protected function updateBatch($rows)
    {
        if (empty($rows)) return;

        $tempTable = 'tmp_refund_' . $this->uploadId . '_' . uniqid();

        // Set charset & collation same as upload_data.waybill_no
        DB::statement("
            CREATE TEMPORARY TABLE $tempTable (
                waybill_no VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY,
                payment_date DATE,
                vendor_type ENUM('Invoice','Vendor') NULL
            )
        ");

        $insertData = array_map(fn($row) => [
            'waybill_no'=>$row['waybill_no'],
            'payment_date'=>$row['payment_date'],
            'vendor_type'  => $row['vendor_type']
        ],$rows);
        DB::table($tempTable)->insert($insertData);

        try {
            DB::update("
                UPDATE upload_data u
                JOIN $tempTable t ON u.waybill_no = t.waybill_no
                SET
                    u.refund = 1,
                    u.refund_id = ?,
                    u.payment_date = t.payment_date,
                    u.vendor_type = t.vendor_type
                WHERE u.refund = 0
            ", [$this->uploadId]);
        } catch (\Throwable $e) {
            Log::error("Batch update failed: ".$e->getMessage());
            throw $e;
        }
    }
}