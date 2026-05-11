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
    protected string $username;
    protected int $uploadId;
    protected string $filePath;
    protected int $batchSize = 1500; //1500 × 33 columns = 49,500,(MySQL default 65,535 limit)

    public $timeout = 3600;
    public $tries = 3;

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
        ]);

        if (!file_exists($this->filePath)) {
            $upload->update([
                'status' => 'failed',
                'error_message' => 'File not found',
                'processed_duration' => round(microtime(true) - $startTime, 2),
            ]);
            return;
        }

        $processed = 0;
        $failed = 0;
        $totalRows = 0;
        $insertData = [];
        $now = now();

        try {
            $file = new \SplFileObject($this->filePath);
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(",");

            $file->rewind();
            $file->fgetcsv(); // skip header

            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if (!$row || $row[0] === null) continue;

                $totalRows++;
                try {
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
                        'receiver_name' => $this->clean($row[15] ?? null, 255),
                        'receiver_mobile' => $row[16] ?? null,
                        'receiver_address' => $this->clean($row[17] ?? null, 255),
                        'recipient_name' => $this->clean($row[18] ?? null, 255),
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

                    if (count($insertData) >= $this->batchSize) {
                        DB::transaction(function () use ($insertData) {
                            DB::table('upload_data')->insert($insertData);
                        });
                        $insertData = [];
                    }

                    // Update progress every batchSize
                    if ($processed % $this->batchSize === 0) {
                        $upload->update([
                            'total_rows' => $totalRows,
                            'processed_rows' => $processed,
                            'failed_rows' => $failed,
                        ]);
                    }

                } catch (\Throwable $rowEx) {
                    if ($failed < 20) {
                        Log::warning("Row failed: " . substr($rowEx->getMessage(), 0, 500));
                    }
                    $failed++;
                }
            }

            // Insert any remaining rows
            if (!empty($insertData)) {
                DB::transaction(function () use ($insertData) {
                    DB::table('upload_data')->insert($insertData);
                });
            }

            $duration = round(microtime(true) - $startTime, 2);
            $upload->update([
                'total_rows' => $totalRows,
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'status' => 'completed',
                'processed_duration' => $duration,
            ]);

            // saved user action logs
            DB::table('action_logs')->insert([
                'action'     => 'IMPORT',
                'keywords'   => 'NO_REFUND',
                'user'       => $this->username,
                'log'        => "Import completed successfully. Upload ID: {$this->uploadId}, Total Rows: {$totalRows}, Success: {$processed}, Failed: {$failed}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AutoCheckAnalyticBranchJob::dispatch($this->uploadId);

        } catch (\Throwable $e) {
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

    protected function clean($value, $length = null)
    {
        if ($value === null) return null;

        // Fix encoding
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        // Fix invalid escaped quotes: \" → "
        $value = str_replace('\\"', '"', $value);

        // Convert " → "" (CSV safe)
        $value = str_replace('"', '""', $value);

        // Remove unwanted control chars (keep Myanmar + Unicode)
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

        $value = trim($value);

        return $length ? mb_substr($value, 0, $length, 'UTF-8') : $value;
    }
}