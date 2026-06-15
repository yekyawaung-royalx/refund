<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PrepareStagingRefundedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public int $uploadId,
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
         * STEP 2
         * Continue processing
         */
        ProcessStagingRefundedJob::dispatch(
            $this->uploadId
        );
    }
}