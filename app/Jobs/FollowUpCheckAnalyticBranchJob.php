<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class FollowUpCheckAnalyticBranchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 3;

    protected int $batchSize = 5000;

    public function handle()
    {
        try {

            $month = Carbon::now()->format('Ym');
            $partition = "P{$month}";

            Log::info("Job started for partition: {$partition}");

            // check partition exists (safe)
            $exists = DB::select("
                SELECT PARTITION_NAME 
                FROM INFORMATION_SCHEMA.PARTITIONS 
                WHERE TABLE_NAME = 'upload_data'
                  AND PARTITION_NAME = ?
            ", [$partition]);

            if (empty($exists)) {
                Log::warning("Partition {$partition} not found");
                return;
            }

            /**
             * =========================
             * FROM ANALYTIC (BATCH LOOP)
             * =========================
             */
            do {
                $affected = DB::affectingStatement("
                    UPDATE upload_data PARTITION ({$partition}) u
                    JOIN analytics a ON u.origin_branch = a.reference
                    SET u.from_analytic_account = a.account
                    WHERE u.from_analytic_account IS NULL
                    LIMIT {$this->batchSize}
                ");

                Log::info("from_analytic_account updated: {$affected}");

            } while ($affected > 0);

            /**
             * =========================
             * TO ANALYTIC (BATCH LOOP)
             * =========================
             */
            do {
                $affected = DB::affectingStatement("
                    UPDATE upload_data PARTITION ({$partition}) u
                    JOIN analytics a ON u.destination_branch = a.reference
                    SET u.to_analytic_account = a.account
                    WHERE u.to_analytic_account IS NULL
                    LIMIT {$this->batchSize}
                ");

                Log::info("to_analytic_account updated: {$affected}");

            } while ($affected > 0);

            Log::info("Job completed for partition: {$partition}");

        } catch (\Throwable $e) {
            Log::error("Job failed: " . $e->getMessage());
            throw $e;
        }
    }
}