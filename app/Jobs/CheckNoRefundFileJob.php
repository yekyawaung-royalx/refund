<?php

namespace App\Jobs;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckNoRefundFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;
    protected string $filePath;

    public $timeout = 1800;
    public $tries = 3;

    protected int $expectedColumns = 33;
    protected int $dbChunkSize = 1000;

    public function __construct(int $uploadId, string $filePath)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
    }

    public function handle()
    {
        $upload = Upload::find($this->uploadId);
        if (!$upload) return;

        $upload->update([
            'status' => 'checking',
            'attempts' => $this->attempts(),
        ]);

        if (!file_exists($this->filePath)) {
            $upload->update([
                'status' => 'failed',
                'error_message' => 'File not found',
            ]);
            return;
        }

        try {
            $file = new \SplFileObject($this->filePath);
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $file->setCsvControl(",");

            // -------------------------
            // 1️⃣ Header Validation
            // -------------------------
            $headers = $file->fgetcsv();
            if (!$headers || count($headers) !== $this->expectedColumns) {
                $upload->update([
                    'status' => 'failed',
                    'error_message' => 'Invalid no refund file.Column structure does not match or file type is incorrect.Column count (must be 33)',
                ]);
                return;
            }

            $rowNumber = 1;
            $errorRows = [];
            $fileWaybills = [];
            $year = null;

            // -------------------------
            // 2️⃣ Row Validation Loop
            // -------------------------
            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if ($row === false || (count($row) === 1 && $row[0] === null)) continue;

                $rowNumber++;

                // Column count check
                if (count($row) !== $this->expectedColumns) {
                    $errorRows[] = "Row {$rowNumber}: Invalid column count";
                    continue;
                }

                // Date validation
                if (empty($row[1]) || !strtotime($row[1])) {
                    $errorRows[] = "Row {$rowNumber}: Invalid or empty outbound date in file.";
                    continue;
                }

                // Extract year for DB duplicate check
                if (!$year) {
                    $year = date('Y', strtotime($row[1]));
                }

                // Waybill validation
                $waybill = trim($row[9] ?? '');
                if (empty($waybill)) {
                    $errorRows[] = "Row {$rowNumber}: Waybill empty";
                    continue;
                }

                // Duplicate in file
                if (isset($fileWaybills[$waybill])) {
                    $errorRows[] = "Row {$rowNumber}: Duplicate waybill in file ({$waybill})";
                    continue;
                }

                $fileWaybills[$waybill] = true;
            }

            // Stop if file-level errors
            if (!empty($errorRows)) {
                $upload->update([
                    'status' => 'failed',
                    'error_message' => substr(implode("\n", $errorRows), 0, 65000),
                ]);
                return;
            }

            // -------------------------
            // 3️⃣ Database Duplicate Check
            // -------------------------
            $waybillNumbers = array_keys($fileWaybills);

            foreach (array_chunk($waybillNumbers, $this->dbChunkSize) as $chunk) {
                $duplicates = DB::table('upload_data')
                    ->whereYear('delivered_date', $year)   // Partition pruning
                    ->whereIn('waybill_no', $chunk)
                    ->pluck('waybill_no')
                    ->toArray();

                foreach ($duplicates as $dup) {
                    $errorRows[] = "Duplicate waybill in database: {$dup}";
                }
            }

            if (!empty($errorRows)) {
                $upload->update([
                    'status' => 'failed',
                    'error_message' => substr(implode("\n", $errorRows), 0, 65000),
                ]);
                return;
            }

            // -------------------------
            // 4️⃣ Validated → Dispatch Import
            // -------------------------
            $upload->update([
                'status' => 'validated',
            ]);

            ImportNoRefundFileJob::dispatch($this->uploadId, $this->filePath);

        } catch (\Throwable $e) {
            Log::error($e);
            $upload->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 65000),
            ]);
            throw $e;
        }
    }
}