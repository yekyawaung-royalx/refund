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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $uploadId
    ) {}

    public function handle()
    {
        $logs = DB::table('failed_logs')
            ->where('upload_id', $this->uploadId)
            ->get();

        if ($logs->isEmpty()) {
            return;
        }

        $folder = now()->format('Y-m');
        $dir = storage_path("app/private/upload-failed/{$folder}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relativePath = "upload-failed/{$folder}/failed_{$this->uploadId}.csv";

        $file = storage_path("app/private/" . $relativePath);

        $handle = fopen($file, 'w');

        fputcsv($handle, [
            'upload_id',
            'waybill_no',
            'reason',
            'row_data'
        ]);

        foreach ($logs as $log) {

            fputcsv($handle, [
                $log->upload_id,
                $log->waybill_no,
                $log->reason,
                $log->row_data,
            ]);
        }

        fclose($handle);

        // save file path
        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'failed_path' => $relativePath,
            ]);

        // clear logs
        DB::table('failed_logs')
            ->where('upload_id', $this->uploadId)
            ->delete();
    }
}