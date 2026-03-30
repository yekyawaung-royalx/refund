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

    protected int $uploadId;
    protected string $filePath;

    public $timeout = 3600;
    public $tries = 3;

    protected int $batchSize = 1000;

    public function __construct(int $uploadId, string $filePath)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
    }

    public function handle()
    {
        $startTime = microtime(true);
        $totalAmount = 0;

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

        // 🔥 failed breakdown
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

            // -------- PASS 1 --------
            $file->rewind();
            $file->fgetcsv();

            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if ($row === false || (count($row) === 1 && $row[0] === null)) continue;
                $totalRows++;
            }

            $upload->update(['total_rows' => $totalRows]);

            // -------- PASS 2 --------
            $file->rewind();
            $file->fgetcsv();

            $batch = [];

            while (!$file->eof()) {

                $row = $file->fgetcsv();
                if (!$row || $row[0] === null) continue;

                try {
                    $amount = $row[0] ?? null;
                    $paymentDate = $row[2] ?? null;
                    $waybillNo = $row[5] ?? null;

                    // 🧠 date normalize
                    if ($paymentDate) {
                        $dt = \DateTime::createFromFormat('Y-m-d', $paymentDate);
                        if (!$dt || $dt->format('Y-m-d') !== $paymentDate) {
                            $dt = \DateTime::createFromFormat('n/j/Y', $paymentDate);
                            if ($dt) {
                                $paymentDate = $dt->format('Y-m-d');
                            } else {
                                $failed++;
                                $failedInvalid++;

                                $failedRows[] = [
                                    'file_id' => $this->uploadId,
                                    'waybill_no' => $waybillNo,
                                    'reason' => 'invalid_data'
                                ];
                                continue;
                            }
                        }
                    }

                    if (!$amount || !$paymentDate || !$waybillNo) {
                        $failed++;
                        $failedInvalid++;

                        $failedRows[] = [
                            'file_id' => $this->uploadId,
                            'waybill_no' => $waybillNo,
                            'reason' => 'invalid_data'
                        ];
                        continue;
                    }

                    if (is_numeric($amount)) {
                        $totalAmount += (float) $amount;
                    }

                    $partition = 'P' . date('Ym', strtotime($paymentDate));

                    $batch[$partition][] = [
                        'waybill_no' => $waybillNo,
                        'payment_date' => $paymentDate
                    ];

                    if (count($batch[$partition]) >= $this->batchSize) {
                        $this->processBatch(
                            $partition,
                            $batch[$partition],
                            $processed,
                            $failed,
                            $failedNotFound,
                            $failedAlreadyRefunded,
                            $failedRows
                        );

                        $batch[$partition] = [];
                    }

                } catch (\Throwable $e) {
                    $failed++;
                    $failedInvalid++;
                }
            }

            // leftover batch
            foreach ($batch as $partition => $rows) {
                if (!empty($rows)) {
                    $this->processBatch(
                        $partition,
                        $rows,
                        $processed,
                        $failed,
                        $failedNotFound,
                        $failedAlreadyRefunded,
                        $failedRows
                    );
                }
            }

            // -------- CSV EXPORT --------
            $failedPath = null;

            if (!empty($failedRows)) {

                $folder = now()->format('Y-m');
                $directory = storage_path("app/private/refund-failed/{$folder}");

                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $fileName = "failed_{$this->uploadId}_" . time() . ".csv";
                $fullPath = "{$directory}/{$fileName}";

                $handle = fopen($fullPath, 'w');

                fputcsv($handle, ['file_id', 'waybill_no', 'failed_reason']);

                foreach ($failedRows as $row) {
                    fputcsv($handle, [
                        $row['file_id'],
                        $row['waybill_no'],
                        $row['reason']
                    ]);
                }

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
        $partition,
        $rows,
        &$processed,
        &$failed,
        &$failedNotFound,
        &$failedAlreadyRefunded,
        &$failedRows
    ) {

        $waybills = array_column($rows, 'waybill_no');

        // 🔥 preload DB data (FAST)
        $existingRows = DB::table('upload_data')
            ->whereIn('waybill_no', $waybills)
            ->get()
            ->keyBy('waybill_no');

        foreach ($rows as $row) {

            $db = $existingRows[$row['waybill_no']] ?? null;

            if (!$db) {
                $failed++;
                $failedNotFound++;

                $failedRows[] = [
                    'file_id' => $this->uploadId,
                    'waybill_no' => $row['waybill_no'],
                    'reason' => 'not_found'
                ];

            } elseif ($db->refund == 1) {
                $failed++;
                $failedAlreadyRefunded++;

                $failedRows[] = [
                    'file_id' => $this->uploadId,
                    'waybill_no' => $row['waybill_no'],
                    'reason' => 'already_refunded'
                ];

            } else {
                $processed++;
            }
        }

        // only update valid ones
        $validRows = array_filter($rows, function ($row) use ($existingRows) {
            return isset($existingRows[$row['waybill_no']]) &&
                   $existingRows[$row['waybill_no']]->refund == 0;
        });

        if (!empty($validRows)) {
            $this->updateBatch($partition, $validRows);
        }
    }

    protected function updateBatch($partition, $rows)
    {
        if (empty($rows)) return;

        $caseSql = [];
        $params = [$this->uploadId];

        foreach ($rows as $row) {
            $caseSql[] = "WHEN ? THEN ?";
            $params[] = $row['waybill_no'];
            $params[] = $row['payment_date'];
        }

        $caseSql = implode(' ', $caseSql);

        $waybillNos = array_column($rows, 'waybill_no');
        $placeholders = implode(',', array_fill(0, count($waybillNos), '?'));

        $params = array_merge($params, $waybillNos);

        DB::update("
            UPDATE upload_data PARTITION ($partition)
            SET
                refund = 1,
                refund_id = ?,
                payment_date = CASE waybill_no
                    $caseSql
                END
            WHERE waybill_no IN ($placeholders)
        ", $params);
    }
}