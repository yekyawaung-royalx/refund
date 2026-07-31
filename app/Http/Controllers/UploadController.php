<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\CheckAllWaybillsFileJob;
use App\Jobs\CheckRefundFileJob;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Upload;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Auth;

class UploadController extends Controller
{   
    public function upload_file(Request $request){
        return Inertia::render('refunds/UploadFile');
    }

    public function uploaded_file(Request $request)
    {

        $request->validate([
            'title' => 'required|string',
            'category' => 'required|string',
            'file' => [
                'required',
                'file',
                'max:81920',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());

                    if ($ext !== 'csv') {
                        $fail('The file must be a CSV file.');
                    }
                },
            ],
        ]);

        /*
        dd([
            'file' => $request->file('file'),
            'client_original_name' => $request->file('file')?->getClientOriginalName(),
            'extension' => $request->file('file')?->getClientOriginalExtension(),
            'mime_type' => $request->file('file')?->getMimeType(),
            'server_mime_type' => $request->file('file')?->getMimeType(),
            'size_kb' => $request->file('file')?->getSize() / 1024,
        ]);
        exit;
        */
        
        $waybillCategories = [
            'sender-postpaid',
            'sender-prepaid',
            'receiver-postpaid',
        ];

        if (in_array($request->category, $waybillCategories)) {
            $category = 'no-refund';
        } else {
            $category = 'refunded';
        }

        $file = $request->file('file');

        $folder = now()->format('Y-m');

        $filename = now()->format('Ymd-His')
            . '-' . Str::substr(Str::uuid(), 0, 8)
            . '.' . $file->getClientOriginalExtension();

        // Store inside storage/app/private/uploads/2026-02/
        $path = $file->storeAs("uploads/{$folder}", $filename);

        // Save upload record (store folder path too)
        $upload = Upload::create([
            'title' => $request->title,
            'category' => $category,
            'type' => $request->category,
            'filename' => $filename,
            'folder' => $folder,
            'file_path' => $path,
            'uploaded_by_id' => auth()->user()->id,
            'uploaded_by_name' => auth()->user()->name,
            'status' => 'pending',
        ]);

        // absolute path
        $absolutePath = storage_path("app/private/{$path}");

        // Dispatch job based on category 
        // sender-postpaid, sender-prepaid, receiver-postpaid, refund
        if ($request->category === 'refunded') { 
            CheckRefundFileJob::dispatch($upload->id, $absolutePath, auth()->user()->name); 
        } else { 
            CheckAllWaybillsFileJob::dispatch($upload->id, $absolutePath, auth()->user()->name, $request->category); 
        }

        return redirect()
            ->route('upload.page')
            ->with('success', 'File uploaded! File validation started.');
    }

    public function uploaded_files()
    {
        $startTime = microtime(true);

        $archiveDb = DB::connection('archive_refund')->getDatabaseName();

        $files = DB::table('uploads')
            ->selectRaw("
                id,
                title,
                category,
                type,
                filename,
                folder,
                file_path,
                failed_path,
                total_rows,
                processed_rows,
                failed_rows,
                status,
                processed_duration,
                attempts,
                error_message,
                uploaded_by_name,
                created_at,
                'primary' as source
            ")
            ->unionAll(
                DB::table("{$archiveDb}.uploads")
                    ->selectRaw("
                        upload_id as id,
                        title,
                        category,
                        type,
                        filename,
                        folder,
                        file_path,
                        failed_path,
                        total_rows,
                        processed_rows,
                        failed_rows,
                        status,
                        processed_duration,
                        attempts,
                        error_message,
                        uploaded_by_name,
                        created_at,
                        'archive' as source
                    ")
            );

        $files = DB::query()
            ->fromSub($files, 'u')
            ->orderByDesc('created_at')
            ->paginate(20);

        $executionTimeMs = round(
            (microtime(true) - $startTime) * 1000,
            2
        );

        return Inertia::render('refunds/UploadedFile',[
            'execution_time_ms' => $executionTimeMs,
            'files' => $files,
        ]);
    }


public function view_file(Request $request, $upload)
{
    $startTime = microtime(true);

    /**
     * ---------------------------------------------------------
     * Performance timing helper
     * ---------------------------------------------------------
     */
    $timings = [];

    $mark = function (string $name, float $startedAt) use (&$timings) {
        $timings[$name] = round(
            (microtime(true) - $startedAt) * 1000,
            2
        );
    };

    /**
     * ---------------------------------------------------------
     * 1. Find upload from primary
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $file = DB::table('uploads')
        ->where('id', $upload)
        ->first();

    $mark('01_upload_primary_query', $stepStart);

    $connection = null;

    /**
     * ---------------------------------------------------------
     * 2. If not found, search archive
     * ---------------------------------------------------------
     */
    if (!$file) {

        $stepStart = microtime(true);

        $file = DB::connection('archive_refund')
            ->table('uploads')
            ->where('id', $upload)
            ->first();

        $connection = 'archive_refund';

        $mark('02_upload_archive_query', $stepStart);
    }

    if (!$file) {
        abort(404);
    }

    /**
     * ---------------------------------------------------------
     * 3. Search parameter
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $search = $request->query('search');

    $mark('03_request_search', $stepStart);

    /**
     * ---------------------------------------------------------
     * 4. Determine column
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $column = $file->category === 'refunded'
        ? 'refund_id'
        : 'norefund_id';

    $mark('04_determine_column', $stepStart);

    /**
     * ---------------------------------------------------------
     * 5. Build upload_data query
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $query = DB::connection($connection)
        ->table('upload_data')
        ->where($column, $upload);

    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->where('customer', 'like', "%{$search}%")
                ->orWhere(
                    'customer_reference_no',
                    'like',
                    "%{$search}%"
                )
                ->orWhere(
                    'phone',
                    'like',
                    "%{$search}%"
                );
        });
    }

    $mark('05_build_upload_data_query', $stepStart);

    /**
     * ---------------------------------------------------------
     * 6. Clone query for debugging SQL
     * ---------------------------------------------------------
     */
    $countQuery = clone $query;
    $dataQuery = clone $query;

    /**
     * ---------------------------------------------------------
     * 7. COUNT query
     *
     * paginate() normally executes COUNT separately.
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $total = $countQuery->count();

    $mark('06_upload_data_count', $stepStart);

    /**
     * ---------------------------------------------------------
     * 8. Data query
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $perPage = 200;

    $page = (int) $request->query('page', 1);

    $results = $dataQuery
        ->orderByDesc('accounting_date')
        ->forPage($page, $perPage)
        ->get();

    $mark('07_upload_data_fetch_200', $stepStart);

    /**
     * ---------------------------------------------------------
     * 9. Build paginator manually
     *
     * This is equivalent to paginate() but allows us to
     * measure COUNT and DATA query separately.
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $results = new \Illuminate\Pagination\LengthAwarePaginator(
        $results,
        $total,
        $perPage,
        $page,
        [
            'path' => $request->url(),
            'query' => $request->query(),
        ]
    );

    $mark('08_build_paginator', $stepStart);

    /**
     * ---------------------------------------------------------
     * 10. Extract waybill numbers
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $waybills = collect($results->items())
        ->pluck('waybill_no')
        ->filter()
        ->toArray();

    $mark('09_extract_waybills', $stepStart);

    /**
     * ---------------------------------------------------------
     * 11. upload_details query
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $details = DB::connection($connection)
        ->table('upload_details')
        ->whereIn('waybill_no', $waybills)
        ->get();

    $mark('10_upload_details_query', $stepStart);

    /**
     * ---------------------------------------------------------
     * 12. keyBy
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    $details = $details->keyBy('waybill_no');

    $mark('11_details_keyBy', $stepStart);

    /**
     * ---------------------------------------------------------
     * 13. Attach details
     * ---------------------------------------------------------
     */
    $stepStart = microtime(true);

    foreach ($results->items() as $item) {
        $item->detail = $details[$item->waybill_no] ?? null;
    }

    $mark('12_attach_details_loop', $stepStart);

    /**
     * ---------------------------------------------------------
     * 14. Total execution time
     * ---------------------------------------------------------
     */
    $executionTimeMs = round(
        (microtime(true) - $startTime) * 1000,
        2
    );

    /**
     * ---------------------------------------------------------
     * 15. Log timings
     * ---------------------------------------------------------
     */
    \Log::info('view_file performance', [
        'upload_id' => $upload,
        'connection' => $connection ?: 'default',
        'category' => $file->category,
        'column' => $column,
        'search' => $search,
        'total_records' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'waybill_count' => count($waybills),
        'timings_ms' => $timings,
        'total_execution_ms' => $executionTimeMs,
    ]);

    return Inertia::render('refunds/ViewUploadedFile', [
        'execution_time_ms' => $executionTimeMs,

        // Useful for frontend debugging
        'performance' => [
            'timings_ms' => $timings,
            'total_ms' => $executionTimeMs,
        ],

        'results' => $results,
        'uploadId' => $upload,
        'file' => $file,
        'search' => $search,
    ]);
}



    public function destroy($id)
    {
        $upload = Upload::findOrFail($id);

        // Use folder column from database
        $folder = $upload->folder; // e.g., "2026-02"
        $filePath = storage_path("app/private/uploads/{$folder}/{$upload->filename}");

        // Delete the file if exists
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        // Delete related upload_data records
        DB::table('upload_data')->where('norefund_id', $id)->delete();
        DB::table('upload_details')->where('upload_id', $id)->delete();

        // Delete upload record itself
        $upload->delete();

        return redirect()
            ->route('uploaded-files')
            ->with('message', 'File and related data deleted successfully.');
    }

    public function recent_activities()
    {
        $recent_activities = DB::table('uploads')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'message' => "{$item->uploaded_by_name} has uploaded file {$item->filename} with title '{$item->title}' and {$item->total_rows} rows.",
                    'datetime' => "{$item->created_at}."
                ];
            });

        return response()->json($recent_activities);
    }

    public function recent_uploaded_files()
    {
        $recent_uploaded_files = DB::table('uploads')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return response()->json($recent_uploaded_files);
    }

    public function recent_exported_files()
    {
        $recent_uploaded_files = DB::table('exports')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return response()->json($recent_uploaded_files);
    }

    public function recent_uploaded_data()
    {
        $recent_uploaded_data = DB::table('upload_data')
            ->select('outbound_date','customer_reference_no','customer','waybill_no','from_city','to_city','receiver_name','refund','created_at')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return response()->json($recent_uploaded_data);
    }

    public function download_uploaded_file($id)
    {
        $export = DB::table('uploads')->where('id', $id)->first();

        if (!$export) {
            abort(404, 'File not found');
        }

        // folder from created_at
        $folder = Carbon::parse($export->created_at)->format('Y-m');

        $filePath = storage_path("app/private/uploads/{$folder}/{$export->filename}");

        if (!file_exists($filePath)) {
            abort(404, 'File does not exist');
        }

        return response()->download($filePath, $export->filename);
    }

   public function download_failed_file($id)
    {
        $export = DB::table('uploads')->where('id', $id)->first();

        if (!$export) {
            abort(404, 'File not found');
        }

        // failed_path
        $filePath = storage_path('app/private/' . $export->failed_path);

        if (!file_exists($filePath)) {
            abort(404, 'File does not exist');
        }

        return response()->download($filePath);
    }
   
}
