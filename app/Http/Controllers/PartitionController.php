<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class PartitionController extends Controller
{


public function index(Request $request)
{
    // Get all monthly partitions
    $partitions = DB::select("
        SELECT 
            PARTITION_NAME,
            PARTITION_DESCRIPTION,
            DATA_LENGTH,
            INDEX_LENGTH
        FROM INFORMATION_SCHEMA.PARTITIONS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'upload_data'
        AND PARTITION_NAME IS NOT NULL
        ORDER BY PARTITION_NAME
    ");

    $collection = collect($partitions);

    // Accurate row count per partition (monthly)
    $rowCounts = DB::table('upload_data')
        ->selectRaw("DATE_FORMAT(delivered_date, '%Y%m') as pname, COUNT(*) as rows")
        ->groupBy('pname')
        ->get()
        ->keyBy('pname'); // 'YYYYMM' => row count

    $collection = $collection->map(function($p) use ($rowCounts) {
        $monthKey = substr($p->PARTITION_NAME, 1); // 'pYYYYMM' → 'YYYYMM'
        return (object) array_merge((array) $p, [
            'TABLE_ROWS' => $rowCounts[$monthKey]->rows ?? 0,
            'TOTAL_MB' => round(($p->DATA_LENGTH + $p->INDEX_LENGTH) / 1024 / 1024, 2),
        ]);
    });

    // Pagination
    $perPage = 10;
    $currentPage = LengthAwarePaginator::resolveCurrentPage();
    $currentItems = $collection->slice(($currentPage - 1) * $perPage, $perPage)->values();

    $paginated = new LengthAwarePaginator(
        $currentItems,
        $collection->count(),
        $perPage,
        $currentPage,
        [
            'path' => request()->url(),
            'query' => request()->query(),
        ]
    );

    // Next monthly partition
    $nextPartitionName = 'P' . now()->addMonth()->format('Ym');
    $exists = $collection->contains(fn($p) => $p->PARTITION_NAME === $nextPartitionName);

    //return $nextPartitionName;
    return Inertia::render('partition/Dashboard', [
        'partitions' => $paginated,
        'next_partition_exists' => $exists,
        'total_partitions' => $collection->count(),
        'next_partition_name' => $nextPartitionName,
    ]);
}

    public function db_monitoring()
    {
        $database = env('DB_DATABASE');

        $tables = DB::select("
            SELECT 
                table_name AS name,
                table_rows AS rows,
                ROUND(data_length / 1024 / 1024, 2) AS data_size_mb,
                ROUND(index_length / 1024 / 1024, 2) AS index_size_mb,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS total_size_mb
            FROM information_schema.tables
            WHERE table_schema = ?
            ORDER BY total_size_mb DESC
        ", [$database]);

        //return $tables;
        return Inertia::render('partition/DbMonitoring', [
            'tables' => $tables,
        ]);
    }
        
}