<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class NoteController extends Controller
{
    public function notes()
    {
        $notes = [
            [
                'title' => 'Laravel Queue & Worker Setup Notes',
                'link' => '/notes/laravel-queue',
            ],
            [
                'title' => 'Laravel Production Deployment',
                'link' => '/notes/laravel-production',
            ],
            [
                'title' => 'MySQL Data Directory Migration',
                'link' => '/notes/mysql-storage-move-ssd200gb',
            ],
            [
                'title' => 'Daily Export Scheduler Setup',
                'link' => '/notes/daily-export-scheduler-setup',
            ],
            [
                'title' => 'CSV Import Validation Rules',
                'link' => '/notes/csv-import-validation-rules',
            ],
        ];

        return Inertia::render('notes/Dashboard', [
            'notes' => $notes,
        ]);
    }


    public function laravel_queue()
    {
        $path = resource_path('js/pages/notes/laravel-queue-notes.md');

        if (!file_exists($path)) {
            abort(404, 'Markdown file not found.');
        }

        $content = file_get_contents($path);

        // JS safe injection using json_encode
        $jsSafeContent = json_encode($content);

        return view('notes.show', ['content' => $jsSafeContent]);
    }

    public function laravel_production()
    {
        $path = resource_path('js/pages/notes/laravel-production-deploy.md');

        if (!file_exists($path)) {
            abort(404, 'Markdown file not found.');
        }

        $content = file_get_contents($path);

        // JS safe injection using json_encode
        $jsSafeContent = json_encode($content);

        return view('notes.show', ['content' => $jsSafeContent]);
    }
    
    public function mysql_storage_move_ssd200gb()
    {
        $path = resource_path('js/pages/notes/mysql-storage-move-ssd200gb.md');

        if (!file_exists($path)) {
            abort(404, 'Markdown file not found.');
        }

        $content = file_get_contents($path);

        // JS safe injection using json_encode
        $jsSafeContent = json_encode($content);

        return view('notes.show', ['content' => $jsSafeContent]);
    }

    
    public function daily_export_scheduler_setup()
    {
        $path = resource_path('js/pages/notes/daily-export-scheduler-setup.md');

        if (!file_exists($path)) {
            abort(404, 'Markdown file not found.');
        }

        $content = file_get_contents($path);

        // JS safe injection using json_encode
        $jsSafeContent = json_encode($content);

        return view('notes.show', ['content' => $jsSafeContent]);
    }

    public function csv_import_validation_rules()
    {
        $path = resource_path('js/pages/notes/validation-rules.md');

        if (!file_exists($path)) {
            abort(404, 'Markdown file not found.');
        }

        $content = file_get_contents($path);

        // JS safe injection using json_encode
        $jsSafeContent = json_encode($content);

        return view('notes.show', ['content' => $jsSafeContent]);
    }
}
