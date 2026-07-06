<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Export;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\FollowUpCheckAnalyticBranchJob;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Jobs\ExportExpressFileJob;
use App\Jobs\ExportSameDayFileJob;
use App\Jobs\BpExportExpressFileJob;
use App\Jobs\DailyRefundSummaryJob;

class RefundController extends Controller
{
    public function main_dashboard(Request $request){
        $startTime = microtime(true);
        $cacheKey = 'main_dashboard_stats';
        
        //Cache::forget('main_dashboard_stats');

        $stats = Cache::remember($cacheKey, 3600, function () {
            $now = now();
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();

            return [
                'total' => [
                    'all_time' => DB::table('upload_data')->count(),
                    'this_month' => DB::table('upload_data')
                        ->whereBetween('accounting_date', [$startOfMonth, $endOfMonth])
                        ->count(),
                ],
                'refund0' => [
                    'all_time' => DB::table('upload_data')->where('refund', 0)->count(),
                    'this_month' => DB::table('upload_data')
                        ->where('refund', 0)
                        ->whereBetween('accounting_date', [$startOfMonth, $endOfMonth])
                        ->count(),
                ],
                'refund1' => [
                    'all_time' => DB::table('upload_data')->where('refund', 1)->count(),
                    'this_month' => DB::table('upload_data')
                        ->where('refund', 1)
                        ->whereBetween('accounting_date', [$startOfMonth, $endOfMonth])
                        ->count(),
                ],
            ];
        });

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return Inertia::render('MainDashboard', [
            'execution_time_ms' => $executionTimeMs,
            'stats' => $stats,
        ]);
    }

    public function refund_dashboard(Request $request){
        $startTime = microtime(true);

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // Total records
        $totalRecords = DB::table('upload_data')->count();
        $totalRecordsThisMonth = DB::table('upload_data')
            ->whereBetween('accounting_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->count();

        // Refund = 0
        $refund0Total = DB::table('upload_data')->where('refund', 0)->count();
        $refund0ThisMonth = DB::table('upload_data')
            ->where('refund', 0)
            ->whereBetween('accounting_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->count();
        $refund0ThisMonthExport = DB::table('upload_data')
            ->where('refund', 0)
            ->where('payment_by', 'Sender Pay')
            ->where('payment_type', 'Postpaid')
            ->whereIn('service_type', [
                'express',
                'same_day_delivery',
            ])
            ->whereBetween('accounting_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->count();

        // Refund = 1
        $refund1Total = DB::table('upload_data')->where('refund', 1)->count();
        $refund1ThisMonth = DB::table('upload_data')
            ->where('refund', 1)
            ->whereBetween('accounting_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->count();

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return Inertia::render('refunds/Dashboard', [
            'execution_time_ms' => $executionTimeMs,
            'stats' => [
                'total' => [
                    'all_time' => $totalRecords,
                    'this_month' => $totalRecordsThisMonth,
                ],
                'refund0' => [
                    'all_time' => $refund0Total,
                    'this_month' => $refund0ThisMonth,
                    'this_month_export' => $refund0ThisMonthExport,
                ],
                'refund1' => [
                    'all_time' => $refund1Total,
                    'this_month' => $refund1ThisMonth,
                ],
            ],
        ]);
    }

    public function refunds(Request $request)
    {
        $startTime = microtime(true);

        $from = $request->query('from')
            ? \Carbon\Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->query('to')
            ? \Carbon\Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfMonth();

        $category = $request->query('category', 'all'); // all | no-refund | refund
        $payment_by = $request->query('payment_by', 'all'); // all | sender-pay | receiver-pay
        $payment_type = $request->query('payment_type', 'all'); // all | prepaid | postpaid

        // Detect partitions
        $current = $from->copy()->startOfMonth();
        $end     = $to->copy()->startOfMonth();

        $usedPartitions = [];

        while ($current <= $end) {
            $usedPartitions[] = 'P' . $current->format('Ym');
            $current->addMonth();
        }

        $query = DB::table('upload_data')
            ->whereBetween('accounting_date', [$from, $to]);

        // Category filter
        if ($category === 'no-refund') {
            $query->where('refund', 0);
        }

        if ($category === 'refunded') {
            $query->where('refund', 1);
        }

        // Payment By filter
        if ($payment_by === 'sender-pay') {
            $query->where('payment_by', 'Sender Pay');
        }
        if ($payment_by === 'receiver-pay') {
            $query->where('payment_by', 'Receiver Pay');
        }

        // Payment Type filter
        if ($payment_type === 'prepaid') {
            $query->where('payment_type', 'Prepaid');
        }

        if ($payment_type === 'postpaid') {
            $query->where('payment_type', 'Postpaid');
        }

        $refunds = $query
            ->orderByDesc('outbound_date')
            ->paginate(200)
            ->withQueryString();
        
        // $waybills = collect($refunds->items())
        //     ->pluck('waybill_no')
        //     ->filter()
        //     ->toArray();

        // $details = DB::table('upload_details')
        //     ->whereIn('waybill_no', $waybills)
        //     ->get()
        //     ->keyBy('waybill_no');

        // foreach ($refunds->items() as $item) {
        //     $item->detail = $details[$item->waybill_no] ?? null;
        // }

        //return $refunds;
        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return Inertia::render('refunds/UploadedData', [
            'execution_time_ms' => $executionTimeMs,
            'used_partitions'   => implode(', ', $usedPartitions),
            'results'           => $refunds,
            'from'              => $from->toDateString(),
            'to'                => $to->toDateString(),
            'category'          => $category,
            'payment_by'        => $payment_by,
            'payment_type'      => $payment_type,
        ]);
    }

    public function refunds_by_customers(Request $request)
    {
        $startTime = microtime(true);

        // Get date range or default current month
        $from = $request->query('from')
            ? \Carbon\Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->query('to')
            ? \Carbon\Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfMonth();

        // Detect which year partitions will be used
        $startYear = $from->year;
        $endYear   = $to->year;

        $usedPartitions = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $usedPartitions[] = 'p' . $year;
        }

        // Aggregation query with group by
        $refunds = DB::table('upload_data')
            ->select(
                'customer_reference_no',
                'confirm_date',
                DB::raw('SUM(express_income_amount) as sum_express_income_amount'),
                DB::raw('SUM(cod_total_amount) as sum_cod_total_amount'),
                DB::raw('SUM(cod_express_income_amount) as sum_cod_express_income_amount'),
                DB::raw('SUM(cod_income_amount) as sum_cod_income_amount'),
                DB::raw('SUM(cod_payable_amount) as sum_cod_payable_amount')
            )
            ->whereBetween('date', [$from, $to])
            ->groupBy('customer_reference_no', 'confirm_date')
            ->orderBy('confirm_date', 'desc')
            ->paginate(200)
            ->withQueryString();

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return Inertia::render('refund/page', [
            'execution_time_ms' => $executionTimeMs,
            'used_partitions'   => implode(', ', $usedPartitions),
            'results'           => $refunds,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exported_files(Request $request){
        $startTime = microtime(true);
        $exports = DB::table('exports')->where('service_type','!=','all')->orderBy('id','desc')->paginate(20);

        $endTime = microtime(true);
        // execution time (seconds)
        $executionTime = $endTime - $startTime;

        // milliseconds
        $executionTimeMs = round($executionTime * 1000, 2);

        //return response()->json($exports);
        return Inertia::render('refunds/ExportedFile',[
            'execution_time_ms' => $executionTimeMs,
            'exports' => $exports,
        ]);
    }

    public function bp_exported_files(Request $request){
        $startTime = microtime(true);
        $exports = DB::table('exports')->where('service_type','all')->orderBy('id','desc')->paginate(20);

        $endTime = microtime(true);
        // execution time (seconds)
        $executionTime = $endTime - $startTime;

        // milliseconds
        $executionTimeMs = round($executionTime * 1000, 2);

        //return response()->json($exports);
        return Inertia::render('refunds/BpExportedFile',[
            'execution_time_ms' => $executionTimeMs,
            'exports' => $exports,
        ]);
    }

    public function job_lists()
    {
        $path = app_path('Jobs');

        $files = File::files($path);

        $jobs = collect($files)->map(function ($file) {
            return 'App\\Jobs\\' . pathinfo($file->getFilename(), PATHINFO_FILENAME);
        });

        return Inertia::render('refunds/JobList',[
            'jobs' => $jobs,
        ]);
    }

    public function scheduler_lists()
    {
        $path = app_path('Console/Commands');

        $files = File::files($path);

        $schedulers = collect($files)->map(function ($file) {
            return 'App\\Console\\Commands\\' . pathinfo($file->getFilename(), PATHINFO_FILENAME);
        });

        return Inertia::render('refunds/SchedulerList',[
            'schedulers' => $schedulers,
        ]);
    }

    public function exported_file(Request $request){
        $date = $request->input('date', now()->format('Ym'));

        ExportExpressFileJob::dispatch($date);
        ExportSameDayFileJob::dispatch($date);

        return response()->json([
            'message' => 'Export started successfully.'
        ]);
    }

    public function bp_exported_file(Request $request){
        $from = $request->input('from', now()->format('Ym'));
        $to = $request->input('to', now()->format('Ym'));

        BpExportExpressFileJob::dispatch($from,$to);

        return response()->json([
            'message' => 'Export started successfully.'
        ]);
    }

    public function download_exported_file($id)
    {
        $export = DB::table('exports')->where('id', $id)->first();

        if (!$export) {
            abort(404, 'File not found');
        }

        // folder from created_at
        $folder = Carbon::parse($export->created_at)->format('Y-m');

        $filePath = storage_path("app/private/exports/{$folder}/{$export->filename}");

        if (!file_exists($filePath)) {
            abort(404, 'File does not exist');
        }

        return response()->download($filePath, $export->filename);
    }

    public function view_exported_file($id)
    {
        $export = Export::findOrFail($id);
        $search = trim(request('search', ''));
        $page   = max((int) request('page', 1), 1);
        $perPage = 200;

        // Demo fixed (you can replace with DB later)
        // created_at → 2026-03
        $date = Carbon::parse($export->created_at)->format('Y-m');
        $filename = $export->filename;

        $filePath = storage_path("app/private/exports/{$date}/{$filename}");

        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        $file = new \SplFileObject($filePath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(",");

        /**
         * HEADER
         */
        $file->rewind();
        $headers = $file->current();

        /**
         * SCAN CSV (SEARCH + COUNT)
         */
        $rowsBuffer = [];
        $totalRows = 0;

        $file->rewind();

        foreach ($file as $index => $row) {

            if (!is_array($row)) {
                continue;
            }

            if ($row === [null] || $index === 0) {
                continue;
            }

            if ($search !== '') {

                $found = false;

                foreach ($row as $cell) {
                    if (stripos((string)$cell, $search) !== false) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    continue;
                }
            }

            $rowsBuffer[] = $row;
            $totalRows++;
        }

        /**
         * PAGINATION SLICE
         */
        $offset = ($page - 1) * $perPage;

        $pageRows = array_slice(
            $rowsBuffer,
            $offset,
            $perPage
        );

        $totalPages = (int) ceil($totalRows / $perPage);

        return Inertia::render('refunds/ViewExportedFile', [
            'filename'   => $filename,
            'headers'    => $headers,
            'rows'       => $pageRows,
            'page'       => $page,
            'perPage'    => $perPage,
            'search'     => $search,
            'totalRows'  => $totalRows,
            'totalPages' => $totalPages,
        ]);
    }

    public function runAutoFix()
    {
        FollowUpCheckAnalyticBranchJob::dispatch();

        return response()->json([
            'message' => 'Analytic fix job dispatched successfully'
        ]);
    }

    public function recent_refund_summaries()
    {
        $recent_refund_summaries = DB::table('refund_summaries')
            ->orderBy('date', 'desc')
            ->paginate(10);

        return response()->json($recent_refund_summaries);
    }

    public function generate_recent_refund_summaries($date){
        DailyRefundSummaryJob::dispatch($date);
    }

    public function search(Request $request)
    {
        $startTime = microtime(true);

        // Supported filters
        $filters = ['waybill_no', 'reference_no', 'customer'];

        // Determine which filter is being used
        $filterBy = null;
        foreach ($filters as $f) {
            if ($request->filled($f)) {
                $filterBy = $f;
                break;
            }
        }

        // Default: waybill_no if nothing specified
        if (!$filterBy) {
            $filterBy = 'waybill_no';
        }

        $queryValue = $request->input($filterBy);

        // Align param to actual table column
        $columnMap = [
            'waybill_no'   => 'waybill_no',
            'reference_no' => 'customer_reference_no', // map reference_no to actual column
            'customer'     => 'customer',
        ];

        $columnName = $columnMap[$filterBy] ?? 'waybill_no';

        // Build query dynamically
        $refundsQuery = DB::table('upload_data');
        if ($queryValue) {
            $refundsQuery->where($columnName, 'like', $queryValue . '%');
        }

        $refunds = $refundsQuery->paginate(200)->withQueryString(); // preserve filter/query in pagination
        
        $waybills = collect($refunds->items())
            ->pluck('waybill_no')
            ->filter()
            ->toArray();

        $details = DB::table('upload_details')
            ->whereIn('waybill_no', $waybills)
            ->get()
            ->keyBy('waybill_no');

        foreach ($refunds->items() as $item) {
            $item->detail = $details[$item->waybill_no] ?? null;
        }

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return Inertia::render('refunds/SearchResult', [
            'execution_time_ms' => $executionTimeMs,
            'results'           => $refunds,
            'search'            => $queryValue,
            'filter_by'         => $filterBy, // current filter param
        ]);

        
    }


    public function download_upload_data(Request $request)
    {
        $from = $request->from ? \Carbon\Carbon::parse($request->from) : now()->startOfMonth();
        $to = $request->to ? \Carbon\Carbon::parse($request->to) : now()->endOfMonth();

        $filename = 'uploaded_data_' . now()->format('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($from, $to, $request) {
            $handle = fopen('php://output', 'w');
            $first = true;

            $query = DB::table('upload_data') // always main table
                ->whereNotNull('accounting_date')
                ->whereBetween('accounting_date', [$from->format('Y-m-d'), $to->format('Y-m-d')]);

            if ($request->category && $request->category !== 'all') {
                $query->where('refund', $request->category === 'refund' ? 1 : 0);
            }

            $query->orderBy('id')->chunk(5000, function ($rows) use ($handle, &$first) {
                foreach ($rows as $row) {
                    $data = (array) $row;

                    if ($first) {
                        fputcsv($handle, array_keys($data));
                        $first = false;
                    }

                    fputcsv($handle, array_values($data));
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', "attachment; filename={$filename}");

        return $response;
    }

    public function updated_recent_refund_summaries(Request $request)
    {
        $request->validate([
            'accounting_date' => ['required', 'date'],
        ]);

        try {

            $date = Carbon::parse($request->accounting_date)->toDateString();

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

            DB::table('refund_summaries')->updateOrInsert(
                [
                    'date' => $date,
                ],
                [
                    'title'             => "{$date}-daily-summary",
                    'to_refund_amount'  => $stats->to_refund_amount ?? 0,
                    'refund_amount'     => $stats->refund_amount ?? 0,
                    'to_refund_rows'    => $stats->to_refund_rows ?? 0,
                    'refund_rows'       => $stats->refund_rows ?? 0,
                    'updated_at'        => now(),
                    'created_at'        => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund summary generated successfully.',
                'data' => [
                    'date' => $date,
                    'to_refund_amount' => (float) ($stats->to_refund_amount ?? 0),
                    'refund_amount' => (float) ($stats->refund_amount ?? 0),
                    'to_refund_rows' => (int) ($stats->to_refund_rows ?? 0),
                    'refund_rows' => (int) ($stats->refund_rows ?? 0),
                ]
            ]);

        } catch (\Throwable $e) {

            Log::error('DailyRefundSummary Error', [
                'date' => $request->accounting_date,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
