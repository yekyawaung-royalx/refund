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
use Carbon\Carbon;

class CheckNoRefundFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $uploadId;
    protected string $filePath;

    public $timeout = 1800;
    public $tries = 1;

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
            // -------------------------
            // Preprocess CSV to fix broken quotes/newlines/backslash
            // -------------------------
            $this->fixBrokenCsv($this->filePath);

            $file = new \SplFileObject($this->filePath);
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $file->setCsvControl(',', '"');

            // -------------------------
            // Header Validation
            // -------------------------
            $headers = $file->fgetcsv();
            if (!$headers || count($headers) !== $this->expectedColumns) {
                $upload->update([
                    'status' => 'failed',
                    'error_message' => 'Invalid no refund file. Column count must be 33.',
                ]);
                return;
            }

            $rowNumber = 1;
            $errorRows = [];
            $fileWaybills = [];
            $fileDate = null;

            // -------------------------
            // Row Validation Loop
            // -------------------------
            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if ($row === false || (count($row) === 1 && $row[0] === null)) continue;

                $rowNumber++;

                // Remove backslashes from all columns
                $row = array_map(fn($col) => $col !== null ? str_replace('\\', '', $col) : $col, $row);

                // -------------------------------
                // Fix extra columns in receiver_name/address
                // -------------------------------
                $addressIndex = 17; // receiver_name / address
                while (count($row) > $this->expectedColumns) {
                    $row[$addressIndex] .= ' ' . array_pop($row);
                }

                while (count($row) < $this->expectedColumns) {
                    $row[] = '';
                }

                // Column count check
                if (count($row) !== $this->expectedColumns) {
                    if (count($errorRows) < 1000) {
                        $errorRows[] = "Row {$rowNumber}: Invalid column count";
                    }
                    continue;
                }

                // Date validation
                if (empty($row[1])) {
                    if (count($errorRows) < 1000) {
                        $errorRows[] = "Row {$rowNumber}: Empty outbound date";
                    }
                    continue;
                }

                try {
                    $date = Carbon::parse($row[1]);
                } catch (\Exception $e) {
                    if (count($errorRows) < 1000) {
                        $errorRows[] = "Row {$rowNumber}: Invalid date format";
                    }
                    continue;
                }

                if (!$fileDate) {
                    $fileDate = $date;
                }

                // Waybill validation
                $waybill = trim($row[9] ?? '');
                if (empty($waybill)) {
                    if (count($errorRows) < 1000) {
                        $errorRows[] = "Row {$rowNumber}: Waybill empty";
                    }
                    continue;
                }

                // Weight validation
                $weight = (float)($row[23] ?? 0);
                if ($weight <= 0) {
                    if (count($errorRows) < 1000) {
                        $errorRows[] = "Row {$rowNumber}: Weight cannot be 0 or empty";
                    }
                    continue;
                }

                // Duplicate in file
                if (isset($fileWaybills[$waybill])) {
                    if (count($errorRows) < 1000) {
                        $errorRows[] = "Row {$rowNumber}: Duplicate waybill in file ({$waybill})";
                    }
                    continue;
                }

                $fileWaybills[$waybill] = true;

                // File size protection
                if (count($fileWaybills) > 200000) {
                    throw new \Exception("File too large");
                }
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
            // Date range calculation (based on file)
            // -------------------------
            if (!$fileDate) {
                throw new \Exception("No valid date found in file");
            }

            $endDate = $fileDate->copy()->endOfMonth();
            $startDate = $fileDate->copy()->subMonths(2)->startOfMonth();

            // -------------------------
            // Database Duplicate Check
            // -------------------------
            $waybillNumbers = array_keys($fileWaybills);

            foreach (array_chunk($waybillNumbers, $this->dbChunkSize) as $chunk) {

                $duplicates = DB::table('upload_data')
                    ->whereBetween('delivered_date', [$startDate, $endDate])
                    ->whereIn('waybill_no', $chunk)
                    ->pluck('waybill_no');

                foreach ($duplicates as $dup) {
                    if (count($errorRows) >= 1000) {
                        break 2;
                    }
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
            // Validated → Dispatch Import
            // -------------------------
            $upload->update([
                'status' => 'validated',
            ]);

            ImportNoRefundFileJob::dispatch($this->uploadId, $this->filePath)
                ->onQueue('import');

        } catch (\Throwable $e) {

            Log::error('CheckNoRefundFileJob failed', [
                'upload_id' => $this->uploadId,
                'error' => $e->getMessage(),
            ]);

            $upload->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 65000),
            ]);

            throw $e;
        }
    }

    private function fixBrokenCsv(string $filePath)
    {
        $content = file_get_contents($filePath);

        // fix wrong escape \" → "
        $content = str_replace('\\"', '"', $content);

        $lines = preg_split("/\r\n|\n|\r/", $content);

        $fixedLines = [];
        $buffer = '';
        $inQuotes = false;

        foreach ($lines as $line) {

            $buffer .= ($buffer === '' ? '' : ' ') . $line;

            $quoteCount = substr_count($line, '"');

            if ($quoteCount % 2 !== 0) {
                $inQuotes = !$inQuotes;
            }

            if (!$inQuotes) {
                $fixedLines[] = $buffer;
                $buffer = '';
            }
        }

        file_put_contents($filePath, implode("\n", $fixedLines));
    }
}