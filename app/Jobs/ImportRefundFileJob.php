<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ImportRefundFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;

    public function __construct(
        public int $uploadId,
        public string $filePath,
        public string $username
    ) {}

    public function handle()
    {
        $file = new \SplFileObject($this->filePath);
        $file->setFlags(\SplFileObject::READ_CSV);

        $file->rewind();
        $file->fgetcsv(); // skip header

        $chunk = [];
        $chunkSize = 1000;
        $index = 0;

        while (!$file->eof()) {
            $row = $file->fgetcsv();

            if (!$row || !isset($row[0])) {
                continue;
            }

            $chunk[] = $row;
            $index++;

            if (count($chunk) >= $chunkSize) {
                ProcessRefundChunkJob::dispatch(
                    $this->uploadId,
                    $chunk,
                    $this->username
                );

                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            ProcessRefundChunkJob::dispatch(
                $this->uploadId,
                $chunk,
                $this->username
            );
        }

        // SAVE TOTAL ROWS
        DB::table('uploads')
            ->where('id', $this->uploadId)
            ->update([
                'total_rows' => $index
            ]);
    }
}