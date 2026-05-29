<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRefundChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    public function __construct(
        public int $uploadId,
        public array $rows,
        public string $username
    ) {}

    public function handle()
    {
        $startTime = microtime(true);

        $processed = 0;
        $failed = 0;

        $failedRows = [];
        $validRows  = [];
        $seen       = [];

        /**
         * =========================
         * 1. VALIDATION
         * =========================
         */
        foreach ($this->rows as $row) {

            try {
                $amountRaw = $row[0] ?? null;
                $paymentDateRaw = $row[2] ?? null;
                $waybillNo = $row[5] ?? null;

                if (!$waybillNo || !$paymentDateRaw || !is_numeric($amountRaw)) {
                    $failed++;
                    $failedRows[] = $this->fail($row, 'invalid_data');
                    continue;
                }

                if (isset($seen[$waybillNo])) {
                    $failed++;
                    $failedRows[] = $this->fail($row, 'duplicate_in_file');
                    continue;
                }

                $seen[$waybillNo] = true;

                $paymentDate = \DateTime::createFromFormat('Y-m-d', $paymentDateRaw)
                    ?: \DateTime::createFromFormat('n/j/Y', $paymentDateRaw);

                if (!$paymentDate) {
                    $failed++;
                    $failedRows[] = $this->fail($row, 'invalid_date');
                    continue;
                }

                $validRows[] = [
                    'waybill_no'   => $waybillNo,
                    'payment_date' => $paymentDate->format('Y-m-d'),
                    'vendor_type'  => match (strtolower(trim($row[6] ?? ''))) {
                        'invoice' => 'Invoice',
                        'vendor'  => 'Vendor',
                        default   => null
                    }
                ];

            } catch (\Throwable $e) {
                $failed++;
                $failedRows[] = $this->fail($row, 'exception');

                Log::error('Row failed', [
                    'upload_id' => $this->uploadId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        /**
         * =========================
         * 2. DB PROCESS
         * =========================
         */
        $dbResult = $this->processBatch($validRows);

        $processed += $dbResult['processed'];
        $failed    += $dbResult['failed_db'];

        /**
         * =========================
         * 3. INSERT FAILED LOGS (ONLY ONCE)
         * =========================
         */
        if (!empty($failedRows)) {
            DB::table('failed_logs')->insert($failedRows);
        }

        /**
         * =========================
         * 4. SAVE FAILED FILE
         * =========================
         */
        $failedPath = $this->saveFailedFile($failedRows);

        /**
         * =========================
         * 5. UPDATE UPLOAD TABLE
         * =========================
         */
        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->increment('processed_rows', $processed);

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->increment('failed_rows', $failed);

        $done = (int) DB::table('uploads')
            ->where('id', $this->uploadId)
            ->selectRaw('processed_rows + failed_rows as done')
            ->value('done');

        $total = (int) DB::table('uploads')
            ->where('id', $this->uploadId)
            ->value('total_rows');

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'status' => ($done >= $total) ? 'completed' : 'processing',
                'processed_duration' => round(microtime(true) - $startTime, 2),
                'failed_path' => $failedPath,
            ]);
    }

    /**
     * =========================
     * DB PROCESS
     * =========================
     */
    private function processBatch(array $rows): array
    {
        if (empty($rows)) {
            return ['processed' => 0, 'failed_db' => 0];
        }

        $waybills = array_column($rows, 'waybill_no');

        $records = DB::table('upload_data')
            ->whereIn('waybill_no', $waybills)
            ->get()
            ->keyBy('waybill_no');

        $valid = [];
        $failedDb = 0;

        foreach ($rows as $row) {

            $wb = $row['waybill_no'];

            if (!isset($records[$wb])) {
                $failedDb++;
                DB::table('failed_logs')->insert([
                    'upload_id' => $this->uploadId,
                    'waybill_no' => $wb,
                    'reason' => 'not_found',
                ]);
                continue;
            }

            if ((int)$records[$wb]->refund === 1) {
                $failedDb++;
                DB::table('failed_logs')->insert([
                    'upload_id' => $this->uploadId,
                    'waybill_no' => $wb,
                    'reason' => 'already_refunded',
                ]);
                continue;
            }

            $valid[] = $row;
        }

        if (!empty($valid)) {

            $tempTable = 'tmp_refund_' . $this->uploadId . '_' . now()->timestamp . '_' . random_int(1000, 9999);

            DB::transaction(function () use ($valid, $tempTable) {

                DB::statement("
                    CREATE TEMPORARY TABLE `$tempTable` (
                        waybill_no VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                        payment_date DATE,
                        vendor_type VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                        UNIQUE KEY uq_waybill (waybill_no)
                    )
                    ENGINE=InnoDB
                    DEFAULT CHARSET=utf8mb4
                    COLLATE=utf8mb4_unicode_ci
                ");

                collect($valid)->chunk(1000)->each(fn ($chunk) =>
                    DB::table($tempTable)->insert($chunk->toArray())
                );

                DB::statement("
                    UPDATE upload_data u
                    JOIN `$tempTable` t 
                        ON u.waybill_no = t.waybill_no
                    SET
                        u.refund = 1,
                        u.refund_id = ?,
                        u.payment_date = t.payment_date,
                        u.vendor_type = t.vendor_type
                    WHERE u.refund = 0
                ", [$this->uploadId]);

            });
        }

        return [
            'processed' => count($valid),
            'failed_db' => $failedDb
        ];
    }

    /**
     * =========================
     * HELPERS
     * =========================
     */
    private function fail(array $row, string $reason): array
    {
        return [
            'upload_id' => $this->uploadId,
            'waybill_no' => $row[5] ?? null,
            'reason' => $reason,
        ];
    }

    private function saveFailedFile(array $failedRows): ?string
    {
        if (empty($failedRows)) return null;

        $folder = now()->format('Y-m');
        $dir = storage_path("app/private/upload-failed/{$folder}");

        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $file = "{$dir}/failed_{$this->uploadId}_" . time() . ".csv";

        $handle = fopen($file, 'w');
        fputcsv($handle, ['upload_id', 'waybill_no', 'reason']);

        foreach ($failedRows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return "upload-failed/{$folder}/" . basename($file);
    }
}