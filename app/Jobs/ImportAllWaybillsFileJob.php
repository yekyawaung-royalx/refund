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
use App\Jobs\AutoCheckAnalyticBranchJob;

class ImportAllWaybillsFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $username;
    protected int $uploadId;
    protected string $filePath;
    protected string $category;

    protected int $batchSize = 1500;

    public $timeout = 3600;
    public $tries = 3;

    public function __construct(int $uploadId, string $filePath, string $username, string $category)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
        $this->username = $username;
        $this->category = $category;
    }

    public function handle()
    {
        $startTime = microtime(true);

        $upload = Upload::find($this->uploadId);
        if (!$upload) return;

        $upload->update([
            'status' => 'processing',
            'attempts' => $this->attempts(),
        ]);

        if (!file_exists($this->filePath)) {
            $upload->update([
                'status' => 'failed',
                'error_message' => 'File not found',
            ]);
            return;
        }

        $now = now();

        $processed = 0;
        $failed = 0;
        $totalRows = 0;
        $insertData = [];
        /**
         * =========================
         * LOAD ONLY 3 MONTHS DATA
         * (partition friendly)
         * =========================
         */
        $existing = DB::table('upload_data')
            ->whereBetween('accounting_date', [
                now()->subMonths(3)->startOfMonth(),
                now()->endOfMonth()
            ])
            ->pluck('waybill_no')
            ->map(fn($v) => strtoupper($v))
            ->flip()
            ->toArray();

        /**
         * =========================
         * FAILED FILE
         * =========================
         */
        $folder = now()->format('Y-m');
        $directory = storage_path("app/private/upload-failed/{$folder}");

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = "failed_{$this->uploadId}_{$this->category}_.csv";
        $failedFilePath = "{$directory}/{$fileName}";
        $failedRelativePath = "upload-failed/{$folder}/{$fileName}";
        $failedFile = null;

        try {
            $file = new \SplFileObject($this->filePath);
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $file->setCsvControl(',');

            $file->rewind();
            $file->fgetcsv(); // header skip

            while (!$file->eof()) {

                $row = $file->fgetcsv();
                if (!$row || $row[0] === null) continue;

                $totalRows++;

                $waybill = strtoupper(trim($row[9] ?? ''));
                if ($waybill === '') continue;

                /**
                 * =========================
                 * DUPLICATE CHECK (FAST)
                 * =========================
                 */
                if (isset($existing[$waybill])) {
                    $failed++;

                    // create file only when first failure happens
                    if ($failedFile === null) {
                        $failedFile = fopen($failedFilePath, 'w');

                        if ($failedFile) {
                            fputcsv($failedFile, ['waybill_no', 'reason']);
                        }
                    }

                    if ($failedFile) {
                        fputcsv($failedFile, [$waybill, 'Duplicate waybill in table']);
                    }

                    continue;
                }

                // mark as seen in current job (avoid duplicate in same file)
                $existing[$waybill] = true;

                $outboundDate = !empty($row[1])
                    ? date('Y-m-d', strtotime($row[1]))
                    : null;

                $deliveredDate = !empty($row[31])
                    ? date('Y-m-d', strtotime($row[31]))
                    : null;

                $accountingDate = match ($this->category) {
                    'sender-prepaid' => $outboundDate,
                    'sender-postpaid', 'receiver-postpaid' => $deliveredDate,
                    default => null,
                };

                /**
                 * =========================
                 * PREP INSERT
                 * =========================
                 */
                $insertData[] = [
                        'norefund_id' => $this->uploadId,
                        'outbound_date' => $outboundDate,
                        'customer_order_reference' => $row[2] ?? null,
                        'customer_reference_no' => $row[3] ?? null,
                        'customer' => $row[4] ?? null,
                        'phone' => $row[5] ?? null,
                        'mobile' => $row[6] ?? null,
                        'operator' => $row[7] ?? null,
                        'pickup_man' => $row[8] ?? null,
                        'waybill_no' => $row[9] ?? null,
                        'from_city' => $row[10] ?? null,
                        'origin_branch' => $row[11] ?? null,
                        'to_city' => $row[12] ?? null,
                        'destination_branch' => $row[13] ?? null,
                        'other' => $row[14] ?? null,
                        'receiver_name' => $this->clean($row[15] ?? null, 255),
                        'receiver_mobile' => $row[16] ?? null,
                        'receiver_address' => $this->clean($row[17] ?? null, 255),
                        'recipient_name' => $this->clean($row[18] ?? null, 255),
                        'recipient_phone' => $this->clean($row[19] ?? null, 255),
                        'payment_by' => $row[20] ?? null,
                        'payment_type' => $row[21] ?? null,
                        'service' => $row[22] ?? null,
                        'weight' => isset($row[23]) ? (float)$row[23] : null,
                        'express_income_amount' => isset($row[24]) ? (float)$row[24] : null,
                        'cod_total_amount' => isset($row[25]) ? (float)$row[25] : null,
                        'cod_express_income_amount' => isset($row[26]) ? (float)$row[26] : null,
                        'cod_income_amount' => isset($row[27]) ? (float)$row[27] : null,
                        'cod_payable_amount' => isset($row[28]) ? (float)$row[28] : null,
                        'insurance_expense_amount' => '0.0',
                        'refund' => 0,
                        'service_type' => $row[29] ?? null,
                        //'waybill_status' => $row[30] ?? null,
                        'waybill_status' => (function () use ($row) {
                            $status = isset($row[30]) ? trim($row[30]) : null;
                            $status = $status === '' ? null : strtoupper($status);
                            $allowed = ['DELIVERED', 'RETURNED', 'CANCELLED', 'COMPLETED'];
                            return in_array($status, $allowed)
                                ? $status
                                : null;
                        })(),
                        'delivered_date' => $deliveredDate,
                        'confirm_date' => $row[32] ? date('Y-m-d', strtotime($row[32])) : null,
                        'accounting_date' => $accountingDate,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                $processed++;

                /**
                 * =========================
                 * BATCH INSERT
                 * =========================
                 */
                if (count($insertData) >= $this->batchSize) {

                    DB::transaction(function () use (&$insertData) {
                        DB::table('upload_data')->insertOrIgnore($insertData);
                    });

                    $insertData = [];
                }
            }


            /**
             * FINAL INSERT
             */
            if (!empty($insertData)) {
                DB::transaction(function () use (&$insertData) {
                    DB::table('upload_data')->insertOrIgnore($insertData);
                });
            }

            $upload->update([
                'status' => 'completed',
                'total_rows' => $totalRows,
                'processed_rows' => $processed,
                'failed_rows' => $failed,
                'failed_path' => $failed > 0 ? $failedRelativePath : null,
                'processed_duration' => round(microtime(true) - $startTime, 2),
            ]);

            DB::table('action_logs')->insert([
                'action' => 'IMPORT',
                'keywords' => 'WAYBILL_IMPORT',
                'user' => $this->username,
                'log' => "Upload {$this->uploadId} done. Total {$totalRows}, OK {$processed}, Failed {$failed}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AutoCheckAnalyticBranchJob::dispatch($this->uploadId);

        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [
                'upload_id' => $this->uploadId,
            ]);

            $upload->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 2000),
            ]);

            throw $e;
        }finally {
            if ($failedFile) {
                fclose($failedFile);
            }
        }
    }


    protected function clean($value, $length = null)
    {
        if ($value === null) return null;

        // Fix encoding
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        // Fix invalid escaped quotes: \" → "
        $value = str_replace('\\"', '"', $value);

        // Convert " → "" (CSV safe)
        $value = str_replace('"', '""', $value);

        // Remove unwanted control chars (keep Myanmar + Unicode)
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

        $value = trim($value);

        return $length ? mb_substr($value, 0, $length, 'UTF-8') : $value;
    }
}