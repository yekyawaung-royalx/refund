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

            /**
             * Update from_analytic_account
             * origin_branch -> analytics.reference
             * only for this upload
             */
            DB::table('upload_data as u')
                ->join('analytics as a', 'u.origin_branch', '=', 'a.reference')
                ->where('u.norefund_id', $this->uploadId)
                ->whereNull('u.from_analytic_account')
                ->update([
                    'u.from_analytic_account' => DB::raw('a.account')
                ]);

            /**
             * Update to_analytic_account
             * destination_branch -> analytics.reference
             * only for this upload
             */
            DB::table('upload_data as u')
                ->join('analytics as a', 'u.destination_branch', '=', 'a.reference')
                ->where('u.norefund_id', $this->uploadId)
                ->whereNull('u.to_analytic_account')
                ->update([
                    'u.to_analytic_account' => DB::raw('a.account')
                ]);

            Log::info("AutoCheckAnalyticBranchJob completed successfully for norefund_id: {$this->uploadId}");

        } catch (\Throwable $e) {
            Log::error("AutoCheckAnalyticBranchJob failed for norefund_id {$this->uploadId}: " . $e->getMessage());
            throw $e;
        }
    }
}