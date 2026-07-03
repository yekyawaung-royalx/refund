<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\PartitionController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ArchiveRefundController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


Route::get('/xlsx', function () {

    $filePath = storage_path('app/private/finance-reports/2026-07/cod-refund-2026-07-02-20260703_114631.xlsx');

    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    $highestRow = $sheet->getHighestRow();
    $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

    echo "<pre>";

    for ($row = 1; $row <= $highestRow; $row++) {

    echo "Row: $row\n";

    for ($col = 1; $col <= $highestColumnIndex; $col++) {

        $coordinate = Coordinate::stringFromColumnIndex($col) . $row;

        $cell = $sheet->getCell($coordinate);

        $value = $cell->getFormattedValue();
        $dataType = $cell->getDataType();
        $phpType = gettype($value);

        echo "Column {$col} => Value: "
            . var_export($value, true)
            . " | DataType: {$dataType}"
            . " | PHPType: {$phpType}\n";
    }

    echo "-----------------------------\n";
}

    echo "</pre>";
});

Route::get('/generate-waybills', function (){
    $total = 3000; 
    $results = []; 

    function randomLetters($length = 4){ 
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; 
            $output = ''; for ($i = 0; $i < $length; $i++) { 
                $output .= $letters[rand(0, 25)]; 
            } 
            return $output; 
        } 
        

        function randomDigits($length = 4){ 
            $digits = ''; for ($i = 0; $i < $length; $i++) { 
                $digits .= rand(0,9); 
            } 
            return $digits; 
        } 

        for ($i = 0; $i < $total; $i++) { 
            $prefix = randomLetters(4); 
            $first = randomDigits(2); 
            $second = randomDigits(4); 
            $third = randomDigits(2); 
            $postfix = randomLetters(4); 
            //$results[] = "{$prefix}{$second}{$postfix}{$third}"; 
            $results[] = "{$first}{$prefix}{$second}{$postfix}{$third}"; 
        }

        return response(implode("\n", $results)) ->header('Content-Type', 'text/plain');
});

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/permissions', function () {
   $permissions = DB::table('permissions')->get();

   return $permissions;
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [RefundController::class, 'main_dashboard'])->name('dashboard');
    Route::get('/refunds', [RefundController::class, 'refund_dashboard'])->name('refunds.dashboard');
    Route::get('/refunds/upload', [UploadController::class, 'upload_file'])->name('upload.page');
    Route::post('/refunds/uploaded-file', [UploadController::class, 'uploaded_file']);
    Route::get('/refunds/uploaded-data', [RefundController::class, 'refunds']);
    Route::get('/refunds/uploaded-data/download', [RefundController::class, 'download_upload_data'])->name('uploaded-data.download');
    Route::get('/refunds/uploaded-files', [UploadController::class, 'uploaded_files'])->name('uploaded-files');
    Route::get('/refunds/uploaded-files/{upload}',[UploadController::class, 'view_file']);
    Route::get('/refunds/uploaded-files/{upload}/download',[UploadController::class, 'download_uploaded_file']);
    Route::get('/refunds/failed-files/{upload}/download',[UploadController::class, 'download_failed_file']);         
    Route::get('/search', [RefundController::class, 'search']);

    Route::get('/refunds-by-customers', [RefundController::class, 'refunds_by_customers']);
    Route::get('/exported-files', [RefundController::class, 'exported_files']);
    Route::get('/exported-files/{id}', [RefundController::class, 'view_exported_file']);
    Route::get('/exported-files/export/download', [RefundController::class, 'exported_file']);
    Route::get('/download-export/{id}', [RefundController::class, 'download_exported_file']);
    Route::get('/bp-exported-files', [RefundController::class, 'bp_exported_files']);
    Route::get('/bp-exported-files/export/download', [RefundController::class, 'bp_exported_file']);
    Route::get('/partitions', [PartitionController::class, 'index'])->name('partition.dashboard');
    Route::get('/db-monitoring', [PartitionController::class, 'db_monitoring']);
    Route::get('/reporting', [ReportingController::class, 'index']);
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'view']);
    Route::patch('/profile/avatar', [UserController::class, 'update_avatar'])->name('profile.update-avatar');
    Route::get('/jobs', [RefundController::class, 'job_lists']);
    Route::get('/schedulers', [RefundController::class, 'scheduler_lists']);
    Route::delete('/refunds/uploaded-files/{id}', [UploadController::class, 'destroy']);
    Route::get('/reporting/search', [ReportingController::class, 'search']);
    Route::post('/users/{user}/permissions', [UserController::class, 'update_permissions']);
    Route::get('/analytics-accounts', [AnalyticsController::class, 'analytics_accounts'])->name('analytics');
    Route::get('/analytics-accounts/create', [AnalyticsController::class, 'create_analytics_accounts']);
    Route::put('/analytics-accounts/update', [AnalyticsController::class, 'update_analytics_accounts']);
    Route::get('/recent-activities', [UploadController::class, 'recent_activities']);
    Route::get('/recent-uploaded-files', [UploadController::class, 'recent_uploaded_files']);
    Route::get('/recent-exported-files', [UploadController::class, 'recent_exported_files']);
    Route::get('/recent-uploaded-data', [UploadController::class, 'recent_uploaded_data']);
    Route::get('/recent-refund-summaries', [RefundController::class, 'recent_refund_summaries']);
    Route::post('/updated-recent-refund-summaries', [RefundController::class, 'updated_recent_refund_summaries']);

    /* Finance Report Routes */
    Route::get('/finance-report/branches-deposit', [ReportingController::class, 'finance_report_branches_deposit']);
    Route::get('/finance-report/branches-deposit/export', [ReportingController::class, 'branches_deposit_export']);
    //Route::get('/finance-report/branches-deposit/generate', [ReportingController::class, 'branches_deposit_generate']);
    Route::get('/finance-report/branches-deposit/exported-files', [ReportingController::class, 'finance_exported_branches_deposit_files']);
    Route::get('/finance-report/branches-deposit/exported-files/{id}', [ReportingController::class, 'view_finance_exported_branches_deposit_files']);
    Route::get('/finance-report/branches-deposit/exported-files/{id}/download',[ReportingController::class, 'download_exported_branches_deposit_file']);  
    Route::get('/finance-report/cod-refund', [ReportingController::class, 'finance_report_cod_refund']);
    Route::get('/finance-report/cod-refund/export', [ReportingController::class, 'cod_refund_export']);
    Route::get('/finance-report/cod-refund/exported-files', [ReportingController::class, 'finance_report_cod_refund_files']);
    Route::get('/finance-report/cod-refund/exported-files/{id}/download',[ReportingController::class, 'download_exported_cod_refund_file']);  
    Route::get('/finance-report/sender-receiver-report', [ReportingController::class, 'finance_report_sender_receiver']);
    Route::get('/finance-report/sender-receiver-report/export', [ReportingController::class, 'sender_receiver_export']);
    Route::get('/finance-report/sender-receiver-report/exported-files', [ReportingController::class, 'finance_report_sender_receiver_files']);
    Route::get('/finance-report/sender-receiver-report/exported-files/{id}/download',[ReportingController::class, 'download_exported_sender_receiver_file']);  

    Route::get('/notes', [NoteController::class, 'notes']);
    Route::get('/notes/laravel-queue', [NoteController::class, 'laravel_queue']);
    Route::get('/notes/laravel-production', [NoteController::class, 'laravel_production']);
    Route::get('/notes/mysql-storage-move-ssd200gb', [NoteController::class, 'mysql_storage_move_ssd200gb']);
    Route::get('/notes/daily-export-scheduler-setup', [NoteController::class, 'daily_export_scheduler_setup']);
    Route::get('/notes/csv-import-validation-rules', [NoteController::class, 'csv_import_validation_rules']);
    Route::get('/notes/configuration', [NoteController::class, 'configuration']);

    Route::get('/logs', [LogController::class, 'logs']);
    Route::get('/logs/view', [LogController::class, 'view'])->name('logs.view');

    //Manual Generate
    Route::get('/generate/recent-refund-summaries/{date}', [RefundController::class, 'generate_recent_refund_summaries']);

    //Archive Data
    Route::get('/archive/{id}', [ArchiveRefundController::class, 'archive']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
