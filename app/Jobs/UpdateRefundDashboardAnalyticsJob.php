<?php

namespace App\Jobs;

use App\Models\RefundDashboardAnalytics;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateRefundDashboardAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $startTime = microtime(true);

        $now = Carbon::now();

        $monthStart = $now->copy()->startOfMonth();
        $nextMonth  = $monthStart->copy()->addMonth();

        Log::info('Refund Dashboard Analytics Job START');

        /*
        |--------------------------------------------------------------------------
        | Calculate Statistics
        |--------------------------------------------------------------------------
        */

        $stats = DB::table('upload_data')
            ->selectRaw("
                COUNT(*) AS total,

                SUM(
                    accounting_date >= ?
                    AND accounting_date < ?
                ) AS total_this_month,

                SUM(
                    refund = 0
                ) AS refund0_total,

                SUM(
                    refund = 0
                    AND accounting_date >= ?
                    AND accounting_date < ?
                ) AS refund0_this_month,

                SUM(
                    refund = 0
                    AND payment_by = 'Sender Pay'
                    AND payment_type = 'Postpaid'
                    AND service_type IN (
                        'express',
                        'same_day_delivery'
                    )
                    AND accounting_date >= ?
                    AND accounting_date < ?
                ) AS refund0_this_month_export,

                SUM(
                    refund = 1
                ) AS refund1_total,

                SUM(
                    refund = 1
                    AND accounting_date >= ?
                    AND accounting_date < ?
                ) AS refund1_this_month
            ", [
                $monthStart,
                $nextMonth,

                $monthStart,
                $nextMonth,

                $monthStart,
                $nextMonth,

                $monthStart,
                $nextMonth,
            ])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Create / Update Single Analytics Row
        |--------------------------------------------------------------------------
        */

        $analytics = RefundDashboardAnalytics::firstOrNew([
            'id' => 1,
        ]);

        $analytics->this_month_all_waybills =
            (int) ($stats->total_this_month ?? 0);

        $analytics->all_waybills =
            (int) ($stats->total ?? 0);

        $analytics->this_month_no_refund_waybills =
            (int) ($stats->refund0_this_month ?? 0);

        $analytics->all_no_refund_waybills =
            (int) ($stats->refund0_total ?? 0);

        $analytics->this_month_refunded_waybills =
            (int) ($stats->refund1_this_month ?? 0);

        $analytics->all_refunded_waybills =
            (int) ($stats->refund1_total ?? 0);

        $analytics->this_month_no_refund_export_waybills =
            (int) ($stats->refund0_this_month_export ?? 0);

        $analytics->save();

        /*
        |--------------------------------------------------------------------------
        | Execution Time
        |--------------------------------------------------------------------------
        */

        $executionTimeMs = round(
            (microtime(true) - $startTime) * 1000,
            2
        );

        Log::info('Refund Dashboard Analytics Job END', [
            'all_waybills' =>
                $analytics->all_waybills,

            'this_month_all_waybills' =>
                $analytics->this_month_all_waybills,

            'all_no_refund_waybills' =>
                $analytics->all_no_refund_waybills,

            'this_month_no_refund_waybills' =>
                $analytics->this_month_no_refund_waybills,

            'all_refunded_waybills' =>
                $analytics->all_refunded_waybills,

            'this_month_refunded_waybills' =>
                $analytics->this_month_refunded_waybills,

            'this_month_no_refund_export_waybills' =>
                $analytics->this_month_no_refund_export_waybills,

            'execution_time_ms' =>
                $executionTimeMs,
        ]);
    }
}