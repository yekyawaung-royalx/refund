<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Jobs\GenerateFinanceReportJob;
use App\Models\FinanceExport;
use Auth;

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

        // Detect monthly partitions (pYYYYMM)
        $current = $from->copy()->startOfMonth();
        $end     = $to->copy()->startOfMonth();

        $usedPartitions = [];

        while ($current <= $end) {
            $usedPartitions[] = 'p' . $current->format('Ym');
            $current->addMonth();
        }

        // Query (partition pruning auto works)
        $refunds = DB::table('upload_data')
            ->whereBetween('delivered_date', [$from, $to])
            ->orderByDesc('outbound_date')
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
        $partition = 'P' . Carbon::parse($date)->format('Ym'); // p202602

        // $data = DB::select("
        //     SELECT *
        //     FROM upload_data PARTITION ($partition)
        //     WHERE DATE(delivered_date) = ?
        // ", [$date]);

        $data = DB::select("
            SELECT *
            FROM upload_data PARTITION ($partition)
            WHERE DATE(delivered_date) = ?
        ", [$date]);

        //$count = $countResult[0]->total ?? 0;
        

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        //return $count;
        //print_r($data); //$data;
        return response()->json([
            'partition_scanned' => $partition,
            'date' => $date,
            'count' => count($data),
            'data' => $data,
            'execution_time_ms' => $executionTimeMs,
        ]);
    }

    public function generate(Request $request)
    {
        // -------------------------
        // Validate input
        // -------------------------
        // $validated = $request->validate([
        //     'delivered_date' => ['required', 'date'],
        //     'branch' => ['nullable', 'string'],
        // ]);

        $deliveredDate = '2026-03-09'; //$validated['delivered_date'];
        $branch = ""; //$validated['branch'] ?? null;

        // -------------------------
        // Dispatch Job (Queue)
        // -------------------------
        GenerateFinanceReportJob::dispatch($deliveredDate, $branch);

        // -------------------------
        // Response
        // -------------------------
        return response()->json([
            'status' => 'queued',
            'message' => 'Finance report job is processing in background',
            'data' => [
                'delivered_date' => $deliveredDate,
                'branch' => $branch,
            ]
        ]);
    }

    
     public function finance_report(Request $request)
    {
        $branches = DB::table('analytics')->where('journal','!=','')->get();

        return Inertia::render('reporting/FinanceReport', [
            'branches' => $branches
        ]);
    }

    public function export(Request $request)
    {
        // GET query parameters
        $user = auth()->user()->name;
        $deliveredDate = $request->query('delivered_date');
        $destinationBranch = $request->query('destination_branch');
        $category = $request->query('category');

        // -------------------------
        // Dispatch Job (Queue)
        // -------------------------
        // Category: all,cod-payable,cod-not-collect,cod-to-collect,cod-zero
        GenerateFinanceReportJob::dispatch($deliveredDate, $destinationBranch, $category, $user);

        // -------------------------
        // Response
        // -------------------------
        return response()->json([
            'status' => 'queued',
            'message' => 'Finance report job is processing in background',
            'data' => [
                'delivered_date' => $deliveredDate,
                'branch' => $destinationBranch,
            ]
        ]);
    }

     public function finance_exported_files(Request $request)
    {
        $files = DB::table('finance_exports')->orderBy('id','desc')->paginate(20);

        return response()->json($files);
    }

    public function view_finance_exported_files($id)
    {
        $export = FinanceExport::findOrFail($id);

        // created_at → 2026-03
        $date = Carbon::parse($export->created_at)->format('Y-m');

        $filename = $export->filename;

        $filePath = storage_path("app/private/finance-reports/{$date}/{$filename}");

        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        $rows = [];

        $file = new \SplFileObject($filePath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(",");

        foreach ($file as $row) {

            if ($row === [null]) {
                continue;
            }

            $rows[] = $row;
        }

        $headers = array_shift($rows);
        
        //return $rows;
        return Inertia::render('reporting/ViewExportedFile', [
            'filename' => $filename,
            'headers' => $headers,
            'rows' => $rows
        ]);
    }

    public function download_exported_file($id)
    {
        $export = DB::table('finance_exports')->where('id', $id)->first();

        if (!$export) {
            abort(404, 'File not found');
        }

        // folder from created_at
        $folder = Carbon::parse($export->created_at)->format('Y-m');

        $filePath = storage_path("app/private/finance-reports/{$folder}/{$export->filename}");

        if (!file_exists($filePath)) {
            abort(404, 'File does not exist');
        }

        return response()->download($filePath, $export->filename);
    }


}
