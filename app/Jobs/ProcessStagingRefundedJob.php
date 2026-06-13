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
     * STEP 1: FETCH CHUNK (staging)
     */
    $rows = DB::table('staging_refunded')
        ->where('upload_id', $this->uploadId)
        ->where('status', 'pending')
        ->where('id', '>', $this->fromId)
        ->orderBy('id')
        ->limit($this->chunkSize)
        ->get();

    if ($rows->isEmpty()) {
        $this->finalizeJob();
        return;
    }

    $waybills = $rows->pluck('waybill_no')->filter()->unique()->values()->all();

    /**
     * STEP 2: MAP upload_data (partition-aware)
     */
    $uploadMap = DB::table('upload_data')
        ->whereIn('waybill_no', $waybills)
        ->get(['waybill_no', 'refund', 'payment_date', 'vendor_type', 'accounting_date'])
        ->keyBy('waybill_no');

    /**
     * LOG partition hint (estimated)
     */
    $partitions = $uploadMap
        ->pluck('accounting_date')
        ->filter()
        ->map(fn ($d) => date('Ym', strtotime($d)))
        ->unique()
        ->values()
        ->all();

    \Log::info('upload_data partition scan', [
        'upload_id' => $this->uploadId,
        'from_id' => $this->fromId,
        'partitions_affected' => $partitions,
        'waybill_count' => count($waybills),
    ]);

    $success = [];
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

        $success[$row->waybill_no] = [
            'id' => $row->id,
            'payment_date' => $row->payment_date,
            'vendor_type'  => $row->vendor_type,
            'accounting_date' => $match->accounting_date,
        ];
    }

    /**
     * STEP 3: BULK UPDATE upload_data (JOIN + partition pruning)
     */
    if (!empty($success)) {

        $waybills = array_keys($success);

        $waybillList = "'" . implode("','", $waybills) . "'";

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
                u.refund = 0
                AND s.upload_id = {$this->uploadId}
                AND s.status = 'pending'
                AND u.waybill_no IN ({$waybillList})
        ");

        /**
         * STEP 4: UPDATE staging_refunded (BULK, NO LOOP)
         */
        $ids = array_column($success, 'id');

        DB::table('staging_refunded')
            ->whereIn('id', $ids)
            ->update([
                'status' => 'processed'
            ]);
    }

    /**
     * STEP 5: FAILED UPDATE
     */
    if (!empty($failedIds)) {

        DB::table('staging_refunded')
            ->whereIn('id', $failedIds)
            ->update([
                'status' => 'failed'
            ]);

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
     * STEP 6: TRACKING
     */
    $duration = round(microtime(true) - $startTime, 2);

    DB::table('uploads')
        ->where('id', $this->uploadId)
        ->incrementEach([
            'processed_rows'     => count($success),
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