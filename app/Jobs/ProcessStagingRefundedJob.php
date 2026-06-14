<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessStagingRefundedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public int $uploadId,
        public int $fromId = 0,
        public int $chunkSize = 2000
    ) {}

    public function handle()
    {
        $startTime = microtime(true);

        /**
         * STEP 1: UPDATE accounting_date (IMPORTANT FIX)
         * staging_refunded <- upload_data
         */
        DB::statement("
            UPDATE staging_refunded s
            JOIN upload_data u
                ON u.waybill_no = s.waybill_no
            SET
                s.accounting_date = u.accounting_date,
                s.updated_at = NOW()
            WHERE
                s.upload_id = {$this->uploadId}
                AND s.status = 'pending'
                AND s.accounting_date IS NULL
        ");

        /**
         * STEP 2: MARK not_found
         */
        DB::statement("
            UPDATE staging_refunded s
            LEFT JOIN upload_data u
                ON u.waybill_no = s.waybill_no
            SET
                s.status = 'failed',
                s.reason = 'not_found',
                s.updated_at = NOW()
            WHERE
                s.upload_id = {$this->uploadId}
                AND s.status = 'pending'
                AND u.waybill_no IS NULL
        ");

        /**
         * STEP 3: MARK already_refunded
         */
        DB::statement("
            UPDATE staging_refunded s
            JOIN upload_data u
                ON u.waybill_no = s.waybill_no
               AND u.accounting_date = s.accounting_date
            SET
                s.status = 'failed',
                s.reason = 'already_refunded',
                s.updated_at = NOW()
            WHERE
                s.upload_id = {$this->uploadId}
                AND s.status = 'pending'
                AND u.refund = 1
        ");

        /**
         * STEP 4: BULK REFUND UPDATE (MAIN LOGIC)
         */
        DB::statement("
            UPDATE upload_data u
            JOIN staging_refunded s
                ON u.waybill_no = s.waybill_no
               AND u.accounting_date = s.accounting_date
            SET
                u.refund = 1,
                u.refund_id = {$this->uploadId},
                u.payment_date = s.payment_date,
                u.vendor_type = s.vendor_type,
                u.updated_at = NOW()
            WHERE
                s.upload_id = {$this->uploadId}
                AND s.status = 'pending'
                AND u.refund = 0
        ");

        /**
         * STEP 5: MARK processed
         */
        DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processed',
                'updated_at' => now(),
            ]);

        /**
         * STEP 6: SUMMARY (FAST)
         */
        $summary = DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->selectRaw("
                SUM(status='processed') as success_count,
                SUM(status='failed') as failed_count
            ")
            ->first();

        /**
         * STEP 7: EXPORT FAILED FILE (optional)
         */
        $failedPath = $this->exportFailedFile();

        /**
         * STEP 8: FINAL UPDATE
         */
        $batchDuration = microtime(true) - $startTime;

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'status' => 'completed',

                'processed_rows' => $summary->success_count ?? 0,
                'failed_rows' => $summary->failed_count ?? 0,

                // 👇 HERE IS THE CORRECT PLACE
                'processed_duration' => $batchDuration,

                'failed_path' => $failedPath,
                'updated_at' => now(),
            ]);

        DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->delete();
    }

    /**
     * FINAL STEP
     */
    private function finalizeJob(): void
    {
        $now = now();

        $summary = DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->selectRaw("
                SUM(status='processed') as success_count,
                SUM(status='failed') as failed_count
            ")
            ->first();

        $failedPath = $this->exportFailedFile();

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'status' => 'completed',
                'processed_rows' => $summary->success_count ?? 0,
                'failed_rows' => $summary->failed_count ?? 0,
                'failed_path' => $failedPath,
                'updated_at' => $now,
            ]);

        // DB::table('staging_refunded')
        //     ->where('upload_id', $this->uploadId)
        //     ->delete();
    }

    /**
     * EXPORT FAILED FILE
     */
    private function exportFailedFile(): ?string
    {
        $exists = DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'failed')
            ->exists();

        if (!$exists) return null;

        $folder = now()->format('Y-m');
        $dir = storage_path("app/private/upload-failed/{$folder}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relativePath = "upload-failed/{$folder}/failed_{$this->uploadId}.csv";
        $fullPath = storage_path("app/private/" . $relativePath);

        $handle = fopen($fullPath, 'w');

        fputcsv($handle, ['waybill_no', 'reason']);

        DB::table('staging_refunded')
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

        return $relativePath;
    }
}