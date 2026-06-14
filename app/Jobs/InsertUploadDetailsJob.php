<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InsertUploadDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $data)
    {
    }

    public function handle()
    {
        if (empty($this->data)) return;

        $start = microtime(true);

        DB::table('upload_details')->insert($this->data);

        Log::info('upload_details_insert_time', [
            'rows' => count($this->data),
            'duration' => round(microtime(true) - $start, 4),
        ]);
    }
}