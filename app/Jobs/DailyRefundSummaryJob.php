<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class DailyRefundSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $accountingDate;

    public function __construct(string $accountingDate)
    {
        $this->accountingDate = $accountingDate;
    }

    public function handle()
    {
        try {

            $date = Carbon::parse($this->accountingDate)->toDateString();

            // -----------------------------------
            // 1. Aggregate upload_data by date
            // -----------------------------------
            $stats = DB::table('upload_data')
                ->whereDate('accounting_date', $date)
                ->where('payment_by', 'Sender Pay')
                ->where('payment_type', 'Postpaid')
                ->whereIn('service_type', [
                    'express',
                    'same_day_delivery'
                ])
                ->selectRaw("
                    SUM(CASE WHEN refund = 0 THEN cod_payable_amount ELSE 0 END) as to_refund_amount,
                    SUM(CASE WHEN refund = 1 THEN cod_payable_amount ELSE 0 END) as refund_amount,
                    SUM(CASE WHEN refund = 0 THEN 1 ELSE 0 END) as to_refund_rows,
                    SUM(CASE WHEN refund = 1 THEN 1 ELSE 0 END) as refund_rows
                ")
                ->first();

            $toRefundAmount = $stats->to_refund_amount ?? 0;
            $refundAmount   = $stats->refund_amount ?? 0;
            $toRefundRows   = $stats->to_refund_rows ?? 0;
            $refundRows     = $stats->refund_rows ?? 0;

            // -----------------------------------
            // 2. Upsert into refund_summaries
            // -----------------------------------
            $existing = DB::table('refund_summaries')
                ->whereDate('date', $date)
                ->first();

            if ($existing) {
                DB::table('refund_summaries')
                    ->where('id', $existing->id)
                    ->update([
                        'title'             => $date . '-daily-summary',
                        'date'              => $date,
                        'to_refund_amount'  => $toRefundAmount,
                        'refund_amount'     => $refundAmount,
                        'to_refund_rows'    => $toRefundRows,
                        'refund_rows'       => $refundRows,
                        'updated_at'        => now(),
                    ]);

                Log::info("Refund summary updated for date: {$date}");

            } else {
                DB::table('refund_summaries')->insert([
                    'title'             => $date . '-daily-summary',
                    'date'              => $date,
                    'to_refund_amount'  => $toRefundAmount,
                    'refund_amount'     => $refundAmount,
                    'to_refund_rows'    => $toRefundRows,
                    'refund_rows'       => $refundRows,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                Log::info("Refund summary created for date: {$date}");
            }

        } catch (\Throwable $e) {
            Log::error("DailyRefundSummaryJob failed: " . $e->getMessage());
            throw $e;
        }
    }
}