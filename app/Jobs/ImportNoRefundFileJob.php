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
use App\Jobs\AutoCheckAnalyticBranchJob;

class ImportNoRefundFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;
    protected string $filePath;

    public $timeout = 3600; // max 1 hour execution
    public $tries = 3;      // retry 3 times if fails

    public function __construct(int $uploadId, string $filePath)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
    }

    public function handle()
    {
        // -----------------------------
        // Start timer for performance tracking
        // -----------------------------
        $startTime = microtime(true);

        $upload = Upload::find($this->uploadId);
        if (!$upload) return;

        // -----------------------------
        // Update status to processing
        // -----------------------------
        $upload->update([
            'status' => 'processing',
            'attempts' => $this->attempts(),
        ]);

        $batchSize = 1000; // batch insert size → InnoDB optimized
        $processed = 0;
        $failed = 0;
        $insertData = [];

        if (!file_exists($this->filePath)) {
            // -----------------------------
            // Fail early if file missing
            // -----------------------------
            $upload->update([
                'status' => 'failed',
                'error_message' => 'File not found',
                'processed_duration' => round(microtime(true) - $startTime, 2)
            ]);
            return;
        }

        try {
            // -----------------------------
            // CSV streaming with SplFileObject
            // Efficient memory usage for large files
            // -----------------------------
            $file = new \SplFileObject($this->filePath);
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(",");

            $file->rewind();
            $headers = $file->fgetcsv(); // skip header

            // -----------------------------
            // Count total rows for progress tracking
            // -----------------------------
            $totalRows = 0;
            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if ($row === false || (count($row) === 1 && $row[0] === null)) continue;
                $totalRows++;
            }

            $upload->update(['total_rows' => $totalRows]);

            // -----------------------------
            // Rewind to start actual import
            // -----------------------------
            $file->rewind();
            $file->fgetcsv(); // skip header
            $now = now();

            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if (!$row || $row[0] === null) continue;

                try {
                    // -----------------------------
                    // Prepare batch data
                    // -----------------------------
                    $insertData[] = [
                        'norefund_id' => $this->uploadId,
                        'outbound_date' => isset($row[1]) ? date('Y-m-d', strtotime($row[1])) : null,
                        'customer_order_reference' => $row[2] ?? null,
                        'customer_reference_no' => $row[3] ?? null,
                        'customer' => $row[4] ?? null,
                        'phone' => $row[5] ?? null,
                        'mobile' => $row[6] ?? null,
                        'operator' => $row[7] ?? null,
                        'pickup_man' => $row[8] ?? null,
                        'waybill_no' => $row[9] ?? null,
                        'from_city' => $row[10] ?? null,
                        'origin_branch' => $row[11] ?? null,
                        'to_city' => $row[12] ?? null,
                        'destination_branch' => $row[13] ?? null,
                        'other' => $row[14] ?? null,
                        'receiver_name' => $row[15] ?? null,
                        'receiver_mobile' => $row[16] ?? null,
                        'receiver_address' => $this->clean($row[17] ?? null, 255),
                        'recipient_name' => $row[18] ?? null,
                        'recipient_phone' => $this->clean($row[19] ?? null, 255),
                        'payment_by' => $row[20] ?? null,
                        'payment_type' => $row[21] ?? null,
                        'service' => $row[22] ?? null,
                        'weight' => isset($row[23]) ? (float)$row[23] : null,
                        'express_income_amount' => isset($row[24]) ? (float)$row[24] : null,
                        'cod_total_amount' => isset($row[25]) ? (float)$row[25] : null,
                        'cod_express_income_amount' => isset($row[26]) ? (float)$row[26] : null,
                        'cod_income_amount' => isset($row[27]) ? (float)$row[27] : null,
                        'cod_payable_amount' => isset($row[28]) ? (float)$row[28] : null,
                        'refund' => 0,
                        'service_type' => $row[29] ?? null,
                        'waybill_status' => $row[30] ?? null,
                        'delivered_date' => $row[31] ? date('Y-m-d', strtotime($row[31])) : $now,
                        'confirm_date' => $row[32] ? date('Y-m-d', strtotime($row[32])) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $processed++;

                    // -----------------------------
                    // Batch insert using InnoDB transaction
                    // Wrap each batch → atomic + redo log optimized
                    // -----------------------------
                    if (count($insertData) >= $batchSize) {
                        DB::transaction(function() use ($insertData) {
                            DB::table('upload_data')->insert($insertData);
                        });
                        $insertData = [];
                    }

                    // -----------------------------
                    // Periodic progress update (every 2000 rows)
                    // -----------------------------
                    if ($processed % 2000 === 0) {
                        $upload->update([
                            'processed_rows' => $processed,
                            'failed_rows' => $failed,
                        ]);
                    }
                } catch (\Throwable $rowEx) {
                    if ($failed < 20) {
                        Log::warning("Row failed: " . substr($rowEx->getMessage(),0,500));
                    }
                    $failed++;
                }
            }

            // -----------------------------
            // Insert remaining rows
            // -----------------------------
            if (!empty($insertData)) {
                DB::transaction(function() use ($insertData) {
                    DB::table('upload_data')->insert($insertData);
                });
            }

            // -----------------------------
            // Update completion status & duration
            // -----------------------------
            $duration = round(microtime(true) - $startTime, 2);
            $upload->update([
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'status' => 'completed',
                'processed_duration' => $duration,
            ]);

            // -----------------------------
            // Dispatch next job for analytics
            // -----------------------------
            AutoCheckAnalyticBranchJob::dispatch($this->uploadId);

        } catch (\Throwable $e) {
            // -----------------------------
            // Catch any job-level exception
            // -----------------------------
            $duration = round(microtime(true) - $startTime, 2);
            Log::error(substr($e->getMessage(), 0, 2000), [
                'upload_id' => $this->uploadId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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

    // --------------------------------------
    // Utility: Clean string values
    // Prevent invalid UTF-8 characters + max length
    // --------------------------------------
    protected function clean($value, $length = null)
    {
        if ($value === null) return null;
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
        return $length ? mb_substr($value, 0, $length, 'UTF-8') : $value;
    }
}