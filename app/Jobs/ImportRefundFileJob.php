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
                'processed_duration' => round(microtime(true) - $startTime, 2)
            ]);
            return;
        }

        $totalRows = 0;
        $processed = 0;
        $failed = 0;
        $batchCount = 0;

        try {

            $file = new \SplFileObject($this->filePath);
            $file->setFlags(
                \SplFileObject::READ_CSV |
                \SplFileObject::SKIP_EMPTY |
                \SplFileObject::DROP_NEW_LINE
            );

            $file->setCsvControl(",");

            // -------- PASS 1 count rows --------

            $file->rewind();
            $file->fgetcsv();

            while (!$file->eof()) {

                $row = $file->fgetcsv();

                if ($row === false || (count($row) === 1 && $row[0] === null)) continue;

                $totalRows++;
            }

            $upload->update([
                'total_rows' => $totalRows
            ]);

            // -------- PASS 2 processing --------

            $file->rewind();
            $file->fgetcsv();

            $batch = [];

            while (!$file->eof()) {

                $row = $file->fgetcsv();

                if (!$row || $row[0] === null) continue;

                try {
                    $amount = $row[0] ?? null;
                    $paymentDate = $row[2] ?? null;
                    $waybillNo   = $row[5] ?? null;

                    if (is_numeric($amount)) {
                        $totalAmount += (float) $amount;
                    }

                    if (!$amount || !$paymentDate || !$waybillNo) {
                        $failed++;
                        continue;
                    }

                    $partition = 'P' . date('Ym', strtotime($paymentDate));

                    $batch[$partition][] = [
                        'waybill_no' => $waybillNo,
                        'payment_date' => $paymentDate
                    ];

                    if (count($batch[$partition]) >= $this->batchSize) {

                        $processed += $this->updateBatch($partition, $batch[$partition]);

                        $batch[$partition] = [];

                        $batchCount++;
                    }

                    if ($processed % 2000 === 0) {
                        $upload->update([
                            'processed_rows' => $processed,
                            'failed_rows' => $failed
                        ]);
                    }

                } catch (\Throwable $rowEx) {

                    if ($failed < 20) {
                        Log::warning("Refund row failed: " . substr($rowEx->getMessage(),0,500));
                    }

                    $failed++;
                }
            }

            foreach ($batch as $partition => $rows) {

                if (!empty($rows)) {

                    $processed += $this->updateBatch($partition, $rows);

                    $batchCount++;
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            $upload->update([
                'status' => 'completed',
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'processed_duration' => $duration,
            ]);

            DailyRefundSummaryJob::dispatch($this->uploadId, null, now()->toDateString(), $totalAmount);

            Log::info('Refund import completed', [
                'upload_id' => $this->uploadId,
                'total_rows' => $totalRows,
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'batches' => $batchCount,
                'total_amount' => $totalAmount,
            ]);

        } catch (\Throwable $e) {

            $duration = round(microtime(true) - $startTime, 2);

            Log::error($e->getMessage(), [
                'upload_id' => $this->uploadId
            ]);

            $upload->update([
                'status' => 'failed',
                'attempts' => $this->attempts(),
                'error_message' => substr($e->getMessage(), 0, 65500),
                'processed_duration' => $duration
            ]);

            throw $e;
        }
    }

    protected function updateBatch($partition, $rows)
    {
        if (empty($rows)) return 0;

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

        return DB::update("
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