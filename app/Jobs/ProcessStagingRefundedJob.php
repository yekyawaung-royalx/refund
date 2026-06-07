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
         * STEP 1: FETCH CHUNK
         */
        $rows = DB::table('staging_refunded')
            ->where('upload_id', $this->uploadId)
            ->where('status', 'pending')
            ->where('id', '>', $this->fromId)
            ->orderBy('id')
            ->limit($this->chunkSize)
            ->get();

        /**
         * STEP 2: LAST CHUNK CHECK
         */
        if ($rows->isEmpty()) {
            $this->finalizeJob();
            return;
        }

        $waybills = $rows->pluck('waybill_no')->all();

        /**
         * STEP 3: LOAD upload_data MAP
         */
        $uploadMap = DB::table('upload_data')
            ->whereIn('waybill_no', $waybills)
            ->get(['waybill_no', 'refund'])
            ->keyBy('waybill_no');

        $successIds = [];
        $successWaybills = [];

        $failedIds = [];
        $failedReasons = [];

        foreach ($rows as $row) {

            $match = $uploadMap[$row->waybill_no] ?? null;

            if (!$match) {
                $failedIds[] = $row->id;
                $failedReasons[$row->id] = 'not_found';
                continue;
            }

            if ((int) $match->refund === 1) {
                $failedIds[] = $row->id;
                $failedReasons[$row->id] = 'already_refunded';
                continue;
            }

            $successIds[] = $row->id;
            $successWaybills[] = $row->waybill_no;
        }

        /**
         * STEP 4: SUCCESS UPDATE
         */
        if (!empty($successWaybills)) {

            DB::table('upload_data')
                ->whereIn('waybill_no', $successWaybills)
                ->where('refund', 0)
                ->update([
                    'refund' => 1,
                    'refund_id' => $this->uploadId,
                    'updated_at' => now(),
                ]);

            DB::table('staging_refunded')
                ->whereIn('id', $successIds)
                ->update([
                    'status' => 'processed'
                ]);
        }

        /**
         * STEP 5: FAILED UPDATE (BATCH SAFE)
         */
        if (!empty($failedIds)) {

            foreach (array_chunk($failedIds, 1000) as $chunk) {

                DB::table('staging_refunded')
                    ->whereIn('id', $chunk)
                    ->update([
                        'status' => 'failed'
                    ]);
            }

            /**
             * save reason (optimized: 1 query per reason type)
             */
            $grouped = [];

            foreach ($failedReasons as $id => $reason) {
                $grouped[$reason][] = $id;
            }

            foreach ($grouped as $reason => $ids) {

                DB::table('staging_refunded')
                    ->whereIn('id', $ids)
                    ->update([
                        'reason' => $reason
                    ]);
            }
        }

        /**
         * STEP 6: DURATION TRACKING (FIXED FORMAT)
         */
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->incrementEach([
                'processed_rows'     => count($successIds),
                'failed_rows'        => count($failedIds),
                'processed_duration' => $duration,
            ]);

        /**
         * STEP 7: NEXT CHUNK
         */
        $lastId = $rows->last()?->id;

        if ($lastId) {
            self::dispatch(
                $this->uploadId,
                $lastId,
                $this->chunkSize
            )->delay(now()->addMilliseconds(100));
        }
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