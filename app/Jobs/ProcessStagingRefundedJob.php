<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
         * STEP 1
         * NOT FOUND
         */
        DB::update("
            UPDATE staging_refunded s
            LEFT JOIN upload_data u
                ON u.waybill_no = s.waybill_no
            SET
                s.status = 'failed',
                s.reason = 'Waybill not found',
                s.updated_at = NOW()
            WHERE
                s.upload_id = ?
                AND s.status = 'pending'
                AND u.waybill_no IS NULL
        ", [$this->uploadId]);

        /**
         * STEP 2
         * ALREADY REFUNDED
         */
        DB::update("
            UPDATE staging_refunded s
            JOIN upload_data u
                ON u.waybill_no = s.waybill_no
            AND u.accounting_date = s.accounting_date
            SET
                s.status = 'failed',
                s.reason = 'Already refunded',
                s.updated_at = NOW()
            WHERE
                s.upload_id = ?
                AND s.status = 'pending'
                AND u.refund = 1
        ", [$this->uploadId]);

        /**
         * STEP 3
         * ACCOUNTING DATE NOT MATCH
         */
        DB::update("
            UPDATE staging_refunded s
            LEFT JOIN upload_data u
                ON u.waybill_no = s.waybill_no
            AND u.accounting_date = s.accounting_date
            SET
                s.status = 'failed',
                s.reason = 'Accounting date not match',
                s.updated_at = NOW()
            WHERE
                s.upload_id = ?
                AND s.status = 'pending'
                AND u.id IS NULL
        ", [$this->uploadId]);

        /**
         * STEP 4
         * AMOUNT DOES NOT MATCH
         */
        DB::update("
            UPDATE staging_refunded
            SET
                status = 'failed',
                reason = CONCAT('Amount must be ', refund_amount),
                updated_at = NOW()
            WHERE
                upload_id = ?
                AND status = 'pending'
                AND ROUND(amount, 2) <> ROUND(refund_amount, 2)
        ", [$this->uploadId]);

        /**
         * STEP 5
         * BULK REFUND UPDATE
         */
        DB::update("
            UPDATE upload_data u
            JOIN staging_refunded s
                ON u.waybill_no = s.waybill_no
            AND u.accounting_date = s.accounting_date
            SET
                u.refund = 1,
                u.refund_id = ?,
                u.payment_date = s.payment_date,
                u.vendor_type = s.vendor_type,
                u.updated_at = NOW()
            WHERE
                s.upload_id = ?
                AND s.status = 'pending'
                AND u.refund = 0
        ", [
            $this->uploadId,
            $this->uploadId,
        ]);

        /**
         * STEP 6
         * MARK PROCESSED
         */
        DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processed',
                'updated_at' => now(),
            ]);

        /**
         * STEP 7
         * SUMMARY
         */
        $summary = DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->selectRaw("
                SUM(status = 'processed') AS success_count,
                SUM(status = 'failed') AS failed_count
            ")
            ->first();

        /**
         * STEP 8
         * EXPORT FAILED FILE
         */
        $failedPath = $this->exportFailedFile();

        /**
         * STEP 9
         * FINAL UPDATE
         */
        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'status' => 'completed',
                'processed_rows' => $summary->success_count ?? 0,
                'failed_rows' => $summary->failed_count ?? 0,
                'processed_duration' => round(
                    microtime(true) - $startTime,
                    2
                ),
                'failed_path' => $failedPath,
                'updated_at' => now(),
            ]);

        /**
         * STEP 10
         * CLEANUP
         */
        Log::info('CLEANUP START');
        DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->delete();
        Log::info('CLEANUP DONE');
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

        DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->delete();
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