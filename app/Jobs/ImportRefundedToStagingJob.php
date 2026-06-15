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
use Carbon\Carbon;

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

        if (!file_exists($this->filePath)) {
            Log::error('File not found', ['file' => $this->filePath]);
            $upload->update(['status' => 'failed']);
            return;
        }

        $upload->update(['status' => 'staging']);

        /*
        Log::info('IMPORT STARTED', [
            'upload_id' => $this->uploadId,
            'file' => $this->filePath
        ]);
        */

        $file = new \SplFileObject($this->filePath);
        $file->setFlags(\SplFileObject::READ_CSV);
        $file->setCsvControl(',');

        $file->rewind();
        $file->fgetcsv(); // skip header

        $batchSize = 2000;
        $batch = [];
        $total = 0;
        $now = now();

        try {
            while (!$file->eof()) {

                $row = $file->fgetcsv();

                if (empty($row) || !is_array($row)) {
                    continue;
                }

                $waybill = isset($row[5]) ? strtoupper(trim($row[5])) : '';

                if ($waybill === '') {
                    continue;
                }

                $batch[] = [
                    'upload_id'    => $this->uploadId,
                    'waybill_no'   => $waybill,
                    'amount'       => $this->toFloat($row[0] ?? null),
                    'payment_date' => $this->parseDate($row[2] ?? null),
                    'vendor_type'  => isset($row[6]) ? trim($row[6]) : null,
                    'status'       => 'pending',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];

                $total++;

                if (count($batch) === $batchSize) {
                    DB::table('staging_refunded')->insert($batch);

                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('staging_refunded')->insert($batch);
            }

            $upload->update([
                'total_rows' => $total,
                'status' => 'processing',
            ]);

            Log::info('IMPORT COMPLETED', [
                'upload_id' => $this->uploadId,
                'total_rows' => $total
            ]);

            PrepareStagingRefundedJob::dispatch($this->uploadId);
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

    private function parseDate($date): ?string
    {
        if (!$date) return null;

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') return null;

        return (float) $value;
    }
}