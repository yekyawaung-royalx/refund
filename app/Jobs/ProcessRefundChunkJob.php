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
        $processed = 0;
        $failed = 0;

        $failedRows = [];
        $validRows = [];

        $seen = [];

        /**
         * =========================
         * 1. ROW VALIDATION
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

                // duplicate inside file
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
         * 2. DB PROCESSING
         * =========================
         */
        $dbResult = $this->processBatch($validRows, $failedRows);

        $processed = $dbResult['processed'];
        $failed += $dbResult['failed_db'];

        /**
         * =========================
         * 3. SAVE FAILED FILE
         * =========================
         */
        $failedPath = $this->saveFailedFile($failedRows);

        /**
         * =========================
         * 4. UPDATE UPLOAD TABLE
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

        $status = ($done >= $total) ? 'completed' : 'processing';

        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'total_rows' => $total,
                'status' => $status,
                'failed_path' => $failedPath,
            ]);
    }

    /**
     * =========================
     * DB BATCH PROCESS
     * =========================
     */
    private function processBatch(array $rows, array &$failedRows): array
    {
        if (empty($rows)) {
            return ['processed' => 0, 'failed_db' => 0];
        }

        $waybills = array_column($rows, 'waybill_no');

        // only existing & not refunded
        $existing = DB::table('upload_data')
            ->whereIn('waybill_no', $waybills)
            ->where('refund', 0)
            ->pluck('waybill_no')
            ->flip();

        $valid = [];
        $failedDb = 0;

        foreach ($rows as $row) {

            $wb = $row['waybill_no'];

            if (!isset($existing[$wb])) {
                $failedDb++;
                $failedRows[] = $this->fail(['waybill_no' => $wb], 'not_found');
                continue;
            }

            $valid[] = $row;
        }

        if (!empty($valid)) {

            DB::transaction(function () use ($valid) {

                $tempTable = 'tmp_refund_' . $this->uploadId . '_' . now()->timestamp . '_' . random_int(1000, 9999);
                
                DB::statement("
                    CREATE TEMPORARY TABLE $tempTable (
                        waybill_no VARCHAR(100)
                            CHARACTER SET utf8mb4
                            COLLATE utf8mb4_unicode_ci,

                        payment_date DATE,

                        vendor_type VARCHAR(20)
                            CHARACTER SET utf8mb4
                            COLLATE utf8mb4_unicode_ci,

                        UNIQUE KEY uq_waybill (waybill_no)
                    )
                    ENGINE=InnoDB
                    DEFAULT CHARSET=utf8mb4
                    COLLATE=utf8mb4_unicode_ci
                ");

                DB::table($tempTable)->insertOrIgnore($valid);

                DB::statement("
                    UPDATE upload_data u
                    JOIN $tempTable t 
                        ON u.waybill_no COLLATE utf8mb4_unicode_ci 
                        = t.waybill_no
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
     * FAILED FORMAT
     * =========================
     */
    private function fail(array $row, string $reason): array
    {
        return [
            'upload_id'  => $this->uploadId,
            'waybill_no' => $row['waybill_no'] ?? $row[5] ?? null,
            'reason'     => $reason,
        ];
    }

    /**
     * =========================
     * SAVE FAILED CSV
     * =========================
     */
    private function saveFailedFile(array $failedRows): ?string
    {
        if (empty($failedRows)) return null;

        $folder = now()->format('Y-m');
        $dir = storage_path("app/private/upload-failed/{$folder}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = "{$dir}/failed_{$this->uploadId}_" . time() . ".csv";

        $handle = fopen($file, 'w');
        fputcsv($handle, ['upload_id', 'waybill_no', 'reason']);

        foreach ($failedRows as $row) {
            fputcsv($handle, [
                $row['upload_id'],
                $row['waybill_no'],
                $row['reason']
            ]);
        }

        fclose($handle);

        return "upload-failed/{$folder}/" . basename($file);
    }
}