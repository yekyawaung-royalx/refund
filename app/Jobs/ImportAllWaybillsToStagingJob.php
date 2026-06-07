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

class ImportAllWaybillsToStagingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;
    protected string $filePath;
    protected string $username;
    protected string $category;

    protected int $batchSize = 2000;

    public function __construct(int $uploadId, string $filePath, string $username, string $category)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
        $this->username = $username;
        $this->category = $category;
    }

    public function handle()
    {
        $upload = Upload::find($this->uploadId);
        if (!$upload) return;

        $upload->update(['status' => 'staging']);

        if (!file_exists($this->filePath)) {
            $upload->update(['status' => 'failed']);
            return;
        }

        $file = new \SplFileObject($this->filePath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $file->rewind();
        $file->fgetcsv(); // skip header

        $now = now();

        $batch = [];
        $total = 0;

        try {
            while (!$file->eof()) {

                $row = $file->fgetcsv();
                if (!$row || !isset($row[9])) continue;

                $waybill = strtoupper(trim($row[9]));
                if ($waybill === '') continue;

                $batch[] = [
                    'upload_id' => $this->uploadId,
                    'waybill_no' => $waybill,
                    'row_data' => json_encode($row),
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $total++;

                if (count($batch) >= $this->batchSize) {
                    DB::table('staging_all_waybills')->insert($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('staging_all_waybills')->insert($batch);
            }

            $upload->update([
                'status' => 'processing',
                'total_rows' => $total,
            ]);

            // NEXT JOB CALL
            ProcessStagingWaybillsJob::dispatch(
                $this->uploadId,
                $this->category
            );

        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['upload_id' => $this->uploadId]);

            $upload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}