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

class ImportRefundedToStagingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    public function __construct(
        public int $uploadId,
        public string $filePath,
        public string $username
    ) {}

    public function handle()
    {
        $upload = Upload::find($this->uploadId);

        if (!$upload) {
            Log::error('Upload not found', ['upload_id' => $this->uploadId]);
            return;
        }

        $upload->update(['status' => 'staging']);

        if (!file_exists($this->filePath)) {
            Log::error('File not found', ['file' => $this->filePath]);

            $upload->update(['status' => 'failed']);
            return;
        }

        Log::info('IMPORT STARTED', [
            'upload_id' => $this->uploadId,
            'file' => $this->filePath
        ]);

        $file = new \SplFileObject($this->filePath);
        $file->setFlags(\SplFileObject::READ_CSV);
        $file->setCsvControl(',');

        $file->rewind();

        // skip header safely
        $file->fgetcsv();

        $batchSize = 2000;
        $batch = [];
        $total = 0;
        $now = now();

        try {

            while (($row = $file->fgetcsv()) !== false) {

                if (!is_array($row) || empty($row)) {
                    continue;
                }

                $waybill = strtoupper(trim($row[5] ?? ''));

                if ($waybill === '') {
                    continue;
                }

                $batch[] = [
                    'upload_id'   => $this->uploadId,
                    'waybill_no'  => $waybill,
                    'row_data'    => json_encode($row),
                    'status'      => 'pending',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                $total++;

                if (count($batch) >= $batchSize) {

                    $inserted = DB::table('staging_refunded')->insert($batch);

                    Log::info('BATCH INSERT', [
                        'count' => count($batch),
                        'result' => $inserted
                    ]);

                    $batch = [];
                }
            }

            // final batch insert
            if (!empty($batch)) {

                $inserted = DB::table('staging_refunded')->insert($batch);

                Log::info('FINAL INSERT', [
                    'count' => count($batch),
                    'result' => $inserted
                ]);
            }

            // update upload summary
            $upload->update([
                'total_rows' => $total,
                'status' => 'processing',
            ]);

            Log::info('IMPORT COMPLETED', [
                'upload_id' => $this->uploadId,
                'total_rows' => $total
            ]);

            // NEXT JOB (staging processing)
            ProcessStagingRefundedJob::dispatch($this->uploadId);
        } catch (\Throwable $e) {

            Log::error('IMPORT FAILED', [
                'upload_id' => $this->uploadId,
                'error' => $e->getMessage()
            ]);

            $upload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}