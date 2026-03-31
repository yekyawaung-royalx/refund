<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    public function logs()
    {   
        $logs = [
            [
                'title' => 'Default Worker Log',
                'path' => 'logs/default-worker.log',
            ],
            [
                'title' => 'Import Worker Log',
                'path' => 'logs/default-worker.log',
            ],
            [
                'title' => 'Worker',
                'path' => 'logs/worker.log',
            ]
        ];

         return Inertia::render('logs/Dashboard', [
            'logs' => $logs,
        ]);
    }

    public function view(Request $request)
    {
        $path = request('path');

        // remove "/storage" prefix
        $cleanPath = str_replace('/storage', '', $path);

        $fullPath = storage_path($cleanPath);

        if (!$cleanPath || !file_exists($fullPath)) {
            abort(404, 'Log file not found');
        }

        $content = file_get_contents($fullPath);

        return Inertia::render('logs/View', [
            'path' => $path,
            'content' => $content,
        ]);
    }
    
}
