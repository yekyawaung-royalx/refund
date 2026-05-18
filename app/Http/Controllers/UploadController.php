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
            'file' => 'required|mimes:csv,xlsx|max:81920'
        ]);

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

    public function uploaded_files(){
        $startTime = microtime(true);
        $files = DB::table('uploads')->orderBy('id','desc')->paginate(20);

        $endTime = microtime(true);
        // execution time (seconds)
        $executionTime = $endTime - $startTime;

        // milliseconds
        $executionTimeMs = round($executionTime * 1000, 2);

        return Inertia::render('refunds/UploadedFile',[
            'execution_time_ms' => $executionTimeMs,
            'files' => $files,
        ]);
    }

    public function view_file(Request $request, $upload)
    {
        $startTime = microtime(true);

        $file = Upload::findOrFail($upload);

        // folder = 2026-03
        [$year, $month] = explode('-', $file->folder);

        $currentPartition = 'P' . $year . $month;

        // previous month
        $prevDate = \Carbon\Carbon::createFromDate($year, $month, 1)->subMonth();
        $prevPartition = 'P' . $prevDate->format('Ym');

        $search = $request->query('search');

        /**
         * category based column
         */
        $column = $file->category === 'refunded'
            ? 'refund_id'
            : 'norefund_id';
        /**
         * query
         */
        $query = DB::table(
            DB::raw("upload_data PARTITION($prevPartition,$currentPartition)")
        )->where($column, $upload);

        /**
         * search filter
         */
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('customer', 'like', "%{$search}%")
                ->orWhere('customer_reference_no', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $results = $query
            ->orderByDesc('outbound_date')
            ->paginate(200)
            ->withQueryString();

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return Inertia::render('refunds/ViewUploadedFile', [
            'execution_time_ms' => $executionTimeMs,
            'used_partitions'   => $prevPartition . ',' . $currentPartition,
            'results'           => $results,
            'uploadId'          => $upload,
            'file'              => $file,
            'search'            => $search,
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
