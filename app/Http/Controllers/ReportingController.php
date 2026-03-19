<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ReportingController extends Controller
{
    public function index(Request $request)
    {
        $startTime = microtime(true);

        // Get date range or default current month
        $from = $request->query('from')
            ? \Carbon\Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->query('to')
            ? \Carbon\Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfMonth();

        // 🔥 Detect monthly partitions (pYYYYMM)
        $current = $from->copy()->startOfMonth();
        $end     = $to->copy()->startOfMonth();

        $usedPartitions = [];

        while ($current <= $end) {
            $usedPartitions[] = 'p' . $current->format('Ym');
            $current->addMonth();
        }

        // Query (partition pruning auto works)
        $refunds = DB::table('upload_data')
            ->whereBetween('date', [$from, $to])
            ->orderByDesc('date')
            ->paginate(200)
            ->withQueryString();

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        //return $refunds;
        return Inertia::render('reporting/Reporting', [
            'execution_time_ms' => $executionTimeMs,
            'used_partitions'   => implode(', ', $usedPartitions),
            'results'           => $refunds,
            'from' => $from->toDateString(),
            'to'   => $to->toDateString(),
        ]);
    }

    public function search(Request $request)
{
    $startTime = microtime(true);
    $date = $request->date; // 2026-02-01

    // Generate partition name
    $partition = 'p' . Carbon::parse($date)->format('Ym'); // p202602

    $data = DB::select("
        SELECT *
        FROM upload_data PARTITION ($partition)
        WHERE date = ?
    ", [$date]);

    $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

    return response()->json([
        'partition_scanned' => $partition,
        'date' => $date,
        'count' => count($data),
        'data' => $data,
        'execution_time_ms' => $executionTimeMs,
    ]);
}
}
