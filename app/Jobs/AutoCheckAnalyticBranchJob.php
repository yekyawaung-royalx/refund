<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoCheckAnalyticBranchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function handle()
    {
        try {
            $startTime = microtime(true);
            // -----------------------------
            // 1. Update from_analytic_account
            // -----------------------------
            $fromUpdated = DB::affectingStatement("
                UPDATE upload_data u
                JOIN analytics a ON u.origin_branch = a.reference
                SET u.from_analytic_account = a.account
                WHERE u.norefund_id = ?
                  AND u.from_analytic_account IS NULL
                  AND u.origin_branch IS NOT NULL
            ", [$this->uploadId]);


            // -----------------------------
            // 2. Update to_analytic_account
            // -----------------------------
            $toUpdated = DB::affectingStatement("
                UPDATE upload_data u
                JOIN analytics a ON u.destination_branch = a.reference
                SET u.to_analytic_account = a.account
                WHERE u.norefund_id = ?
                  AND u.to_analytic_account IS NULL
                  AND u.destination_branch IS NOT NULL
            ", [$this->uploadId]);

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            // -----------------------------
            // 3. Logging (single line, clean)
            // -----------------------------
            Log::info(
                "Analytic update completed | upload_id: {$this->uploadId} | " .
                "from_updated: {$fromUpdated} | to_updated: {$toUpdated} | durations: {$duration} "
            );


        } catch (\Throwable $e) {

            Log::error(
                "AutoCheckAnalyticBranchJob failed | upload_id={$this->uploadId} | error=" .
                $e->getMessage()
            );

            throw $e;
        }

        // Optional next job
        // FollowUpCheckAnalyticBranchJob::dispatch();
    }
}