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
    
    protected ?int $fileId;
    protected ?int $totalRows;
    protected ?string $paymentDate;
    protected ?float $totalAmount;

    public function __construct(?int $fileId = null, ?int $totalRows = null, ?string $paymentDate = null, ?float $totalAmount = null)
    {
        $this->fileId = $fileId;
        $this->totalRows = $totalRows;
        $this->paymentDate = $paymentDate;
        $this->totalAmount = $totalAmount;
    }

    public function handle()
    {
        try {

            $today = Carbon::today()->toDateString();

            // -----------------------------
            // Get or create today's summary
            // -----------------------------
            $summary = DB::table('refund_summaries')
                ->whereDate('created_at', $today)
                ->first();

            if (!$summary) {
                $summaryId = DB::table('refund_summaries')->insertGetId([
                    'refund_amount' => 0,
                    'to_refund_amount' => 0,
                    'to_refund_rows' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $summary = DB::table('refund_summaries')->where('id', $summaryId)->first();
            }

            // -----------------------------
            // CASE 1: fileId flow
            // -----------------------------
            if ($this->fileId && is_null($this->paymentDate)) {

                $sumAmount = DB::table('upload_data')
                    ->where('export_id', $this->fileId)
                    ->sum('cod_payable_amount');

                DB::table('refund_summaries')
                    ->where('id', $summary->id)
                    ->update([
                        'to_refund_amount' => DB::raw("to_refund_amount + {$sumAmount}"),
                        'to_refund_rows'   => DB::raw("to_refund_rows + {$this->totalRows}"),
                        'updated_at'       => now(),
                    ]);

                Log::info("Refund summary updated (fileId): {$this->fileId}");

                return;
            }

            // -----------------------------
            // CASE 2: payment_date flow
            // -----------------------------
            if (!is_null($this->paymentDate)) {

                // prevent duplicate
                if (!is_null($summary->last_upload_id) && $summary->last_upload_id == $this->fileId) {
                    Log::warning("Duplicate job skipped", [
                        'upload_id' => $this->fileId
                    ]);
                    return;
                }

                $sumAmount = $this->totalAmount ?? 0;

                DB::table('refund_summaries')
                    ->where('id', $summary->id)
                    ->update([
                        'refund_amount' => DB::raw("refund_amount + {$sumAmount}"),
                        'refund_rows' => 100,
                        'last_upload_id' => $this->fileId,
                        'updated_at' => now(),
                    ]);

                return;
            }

        } catch (\Throwable $e) {
            Log::error("DailyRefundSummaryJob failed: " . $e->getMessage());
            throw $e;
        }
    }
}