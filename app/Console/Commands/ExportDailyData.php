<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ExportFileJob;
use App\Jobs\ExportExpressFileJob;
use App\Jobs\ExportSameDayFileJob;

class ExportDailyData extends Command
{
    protected $signature = 'export:daily {date?}';
    protected $description = 'Export daily upload data to CSV';

    public function handle()
    {
        $date = $this->argument('date');

        (new ExportExpressFileJob($date))->handle();
        (new ExportSameDayFileJob($date))->handle();

        $this->info('Export completed.');
    }
}