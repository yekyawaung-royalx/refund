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
use Carbon\Carbon;

class ImportAllWaybillsToStagingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;
    protected string $filePath;
    protected string $username;
    protected string $category;

    protected int $batchSize = 1000;

    public function __construct(int $uploadId, string $filePath, string $username, string $category)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
        $this->username = $username;
        $this->category = $category;
    }

    public function handle(): void
    {
        $upload = Upload::find($this->uploadId);

        if (!$upload) return;

        $upload->update(['status' => 'staging']);

        if (!file_exists($this->filePath)) {
            $upload->update(['status' => 'failed']);
            return;
        }

        /**
         * 🔥 PRELOAD analytics (FAST)
         * reference => account
         */
        $analytics = DB::table('analytics')
            ->pluck('account', 'reference')
            ->toArray();

        $file = new \SplFileObject($this->filePath);
        $file->setFlags(
            \SplFileObject::READ_CSV |
            \SplFileObject::SKIP_EMPTY
        );
        $file->setCsvControl(',');

        $file->rewind();
        $file->fgetcsv(); // skip header

        $batch = [];
        $total = 0;
        $now = now();

        try {

            while (!$file->eof()) {

                $row = $file->fgetcsv();

                if (!$row || !isset($row[9])) continue;

                $waybill = strtoupper(trim($row[9] ?? ''));

                if ($waybill === '') continue;

                /**
                 * CSV mapping (based on your structure)
                 * adjust if index differs
                 */
                $fromRef = trim($row[11] ?? ''); // YGN-SDGN
                $toRef   = trim($row[13] ?? ''); // MGU

                $batch[] = [
                    'upload_id' => $this->uploadId,
                    'outbound_date' => $this->parseDate($row[1] ?? null),
                    'customer_order_reference' => $row[3] ?? null,
                    'customer_reference_no' => $row[3] ?? null,
                    'customer' => $row[4] ?? null,
                    'phone' => $row[5] ?? null,
                    'mobile' => $row[6] ?? null,
                    'operator' => $row[7] ?? null,
                    'pickup_man' => $row[8] ?? null,
                    'waybill_no' => $waybill,
                    'from_city' => $row[10] ?? null,
                    'origin_branch' => $fromRef,
                    'from_analytic_account' => $analytics[$fromRef] ?? null,
                    'to_city' => $row[12] ?? null,
                    'destination_branch' => $toRef,
                    'to_analytic_account' => $analytics[$toRef] ?? null,
                    'other' => $row[14] ?? null,
                    'receiver_name' => $this->clean($row[15] ?? null, 255),
                    'receiver_mobile' => $this->clean($row[16] ?? null, 255),
                    'receiver_address' => $this->clean($row[17] ?? null, 255),
                    'recipient_name' => $this->clean($row[18] ?? null, 255),
                    'recipient_phone' => $this->clean($row[19] ?? null, 255),
                    'payment_by' => $row[20] ?? null,
                    'payment_type' => $row[21] ?? null,
                    'service' => $row[22] ?? null,
                    'weight' => $this->toFloat($row[23] ?? null),
                    'express_income_amount' => $this->toFloat($row[24] ?? null),
                    'cod_total_amount' => $this->toFloat($row[25] ?? null),
                    'cod_express_income_amount' => $this->toFloat($row[26] ?? null),
                    'cod_income_amount' => $this->toFloat($row[27] ?? null),
                    'cod_payable_amount' => $this->toFloat($row[28] ?? null),
                    'insurance_expense_amount' => 0.0,
                    'service_type' => $row[29] ?? null,
                    'waybill_status' => $this->safeStatus($row[30] ?? null),
                    'delivered_date' => $this->parseDate($row[31] ?? null),
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

            Log::error('Import failed', [
                'upload_id' => $this->uploadId,
                'message' => $e->getMessage(),
            ]);

            $upload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Convert date safely
     */
    private function parseDate($date): ?string
    {
        if (!$date) return null;

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convert number safely
     */
    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') return null;

        return (float) $value;
    }

    private function safeStatus($s): ?string
    {
        $s = strtoupper(trim((string)$s));
        return in_array($s, ['DELIVERED','RETURNED','CANCELLED','COMPLETED','REJECTED']) ? $s : null;
    }

    private function clean($v, $len = null)
    {
        if ($v === null) return null;
        $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v);
        return $len ? mb_substr(trim($v), 0, $len) : trim($v);
    }
}