<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ExportFailedLogsJob implements ShouldQueue
{
    public function __construct(public int $uploadId) {}

    public function handle()
    {
        $folder = now()->format('Y-m');
        $dir = storage_path("app/private/upload-failed/{$folder}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relativePath = "upload-failed/{$folder}/failed_{$this->uploadId}.csv";
        $filePath = storage_path("app/private/" . $relativePath);

        $handle = fopen($filePath, 'w');

        fputcsv($handle, ['waybill_no', 'reason']);

        DB::table('staging_data')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'failed')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) use ($handle) {

                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->waybill_no,
                        $row->reason
                    ]);
                }
            });

        fclose($handle);

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'failed_path' => $relativePath
            ]);
    }
}