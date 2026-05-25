<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Jobs\GenerateFinanceBranchBankDepositJob;
use App\Jobs\GenerateFinanceCodRefundJob;
use App\Jobs\GenerateFinanceSenderReceiverJob;
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
            ->whereBetween('accounting_date', [$from, $to])
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

        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : now()->startOfDay();

        $endDate = $date->copy()->endOfDay();

        // Partition name
        $partition = 'P' . $date->format('Ym');

        $query = DB::table(DB::raw("upload_data PARTITION ($partition)"))
            ->where('accounting_date', '>=', $date)
            ->where('accounting_date', '<=', $endDate);

        $results = $query
            ->orderByDesc('accounting_date')
            ->paginate(200)
            ->withQueryString();

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);


        return response()->json([
            'partition_scanned' => $partition,
            'date' => $date->toDateString(),
            'count' => 0,
            'data' => $results,
            'execution_time_ms' => $executionTimeMs,
        ]);

        // return Inertia::render('search/UploadedData', [
        //     'execution_time_ms' => $executionTimeMs,
        //     'partition_scanned' => $partition,
        //     'data' => $results,
        //     'count' => 0,
        //     'date' => $date->toDateString(),
        // ]);
    }

    // public function search(Request $request)
    // {
    //     $startTime = microtime(true);
    //     $date = $request->date; // 2026-02-01

    //     // Generate partition name
    //     $partition = 'P' . Carbon::parse($date)->format('Ym'); // p202602

    //     $data = DB::select("
    //         SELECT *
    //         FROM upload_data PARTITION ($partition)
    //         WHERE DATE(delivered_date) = ?
    //     ", [$date]);

    //     //$count = $countResult[0]->total ?? 0;
        

    //     $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

    //     //return $count;
    //     //print_r($data); //$data;
    //     return $data;
    //     return response()->json([
    //         'partition_scanned' => $partition,
    //         'date' => $date,
    //         'count' => count($data),
    //         'data' => $data,
    //         'execution_time_ms' => $executionTimeMs,
    //     ]);
    // }

    public function branches_deposit_generate(Request $request)
    {
        // -------------------------
        // Validate input
        // -------------------------
        // $validated = $request->validate([
        //     'delivered_date' => ['required', 'date'],
        //     'branch' => ['nullable', 'string'],
        // ]);

        $deliveredDate = $validated['delivered_date'];
        $branch = $validated['branch'] ?? null;

        // -------------------------
        // Dispatch Job (Queue)
        // -------------------------
        GenerateFinanceBranchBankDepositJob::dispatch($deliveredDate, $branch,  auth()->user()->name);

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

    
    public function finance_report_branches_deposit(Request $request)
    {
        $branches = DB::table('analytics')->where('journal','!=','')->get();

        return Inertia::render('reporting/FinanceReportBranchDeposit', [
            'branches' => $branches
        ]);
    }

    public function finance_report_cod_refund(Request $request)
    {
        $branches = DB::table('analytics')->where('journal','!=','')->get();

        return Inertia::render('reporting/FinanceReportCodRefund', [
            'branches' => $branches
        ]);
    }

    public function finance_report_sender_receiver(Request $request)
    {
        $branches = DB::table('analytics')->where('journal','!=','')->get();

        return Inertia::render('reporting/FinanceSenderReceiverReport', [
            'branches' => $branches
        ]);
    }

    public function branches_deposit_export(Request $request)
    {
        // GET query parameters
        $accountingDate = $request->query('accounting_date');
        $destinationBranch = $request->query('destination_branch');
        $category = $request->query('category');

        // -------------------------
        // Dispatch Job (Queue)
        // -------------------------
        // Category: all,cod-payable,cod-not-collect,cod-to-collect,cod-zero
        GenerateFinanceBranchBankDepositJob::dispatch($accountingDate, $destinationBranch, $category, $user = auth()->user()->name);

        // -------------------------
        // Response
        // -------------------------
        return response()->json([
            'status' => 'queued',
            'message' => 'Finance report job is processing in background',
            'data' => [
                'accounting_date' => $accountingDate,
                'branch' => $destinationBranch,
            ]
        ]);
    }

    public function cod_refund_export(Request $request)
    {
        // GET query parameters
        $paymentDate = $request->query('payment_date');
        $category = $request->query('category');

        // -------------------------
        // Dispatch Job (Queue)
        // -------------------------
        // Category: all,cod-payable,cod-not-collect,cod-to-collect,cod-zero
        GenerateFinanceCodRefundJob::dispatch($paymentDate, $category, auth()->user()->name);

        // -------------------------
        // Response
        // -------------------------
        return response()->json([
            'status' => 'queued',
            'message' => 'Finance report job is processing in background',
            'data' => [
                'payment_date' => $paymentDate,
            ]
        ]);
    }

    public function sender_receiver_export(Request $request)
    {
        // GET query parameters
        $accountingDate = $request->query('accounting_date');
        $category = $request->query('category');

        // -------------------------
        // Dispatch Job (Queue)
        // -------------------------
        // Category: all, sender-pay-prepaid , receiver-pay-postpaid
        GenerateFinanceSenderReceiverJob::dispatch($accountingDate, $category, auth()->user()->name);

        // -------------------------
        // Response
        // -------------------------
        return response()->json([
            'status' => 'queued',
            'message' => 'Finance report job is processing in background',
            'data' => [
                'accounting_date' => $accountingDate,
            ]
        ]);
    }

    public function finance_exported_branches_deposit_files(Request $request)
    {
        $files = DB::table('finance_exports')->where('report_type','branches-deposit')->orderBy('id','desc')->paginate(20);

        return response()->json($files);
    }

    public function finance_report_cod_refund_files(Request $request)
    {
        $files = DB::table('finance_exports')->where('report_type','cod-refund')->orderBy('id','desc')->paginate(20);

        return response()->json($files);
    }

     public function finance_report_sender_receiver_files(Request $request)
    {
        $files = DB::table('finance_exports')->where('report_type','sender-receiver')->orderBy('id','desc')->paginate(20);

        return response()->json($files);
    }

    public function view_finance_exported_branches_deposit_files($id)
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
        
        $rows = array_filter($rows, function ($row) {
            return is_array($row);
        });
        
        return Inertia::render('reporting/ViewExportedFile', [
            'filename' => $filename,
            'headers' => $headers,
            'rows' => $rows
        ]);
    }

    public function download_exported_branches_deposit_file($id)
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

    public function download_exported_cod_refund_file($id)
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

    public function download_exported_sender_receiver_file($id)
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
