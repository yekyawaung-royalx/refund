<?php

namespace App\Jobs;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckAllWaybillsFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected string $username;
    protected int $uploadId;
    protected string $filePath;
    protected string $category;

    public $timeout = 1800;
    public $tries = 1;

    
    private array $paymentRules = [];

    public function __construct(int $uploadId, string $filePath, string $username, string $category)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
        $this->username = $username;
        $this->category = $category;
        $this->paymentRules = config('payment_rules') ?? [];
    }

    public function handle()
    {
        $upload = Upload::find($this->uploadId);
        if (!$upload) return;

        $upload->update([
            'status' => 'checking',
            'attempts' => $this->attempts(),
        ]);

        $expectedColumns = $this->getExpectedColumns();

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
            //if (!$headers || count($headers) !== $this->expectedColumns) {
            if (!$headers || count($headers) !== $expectedColumns){
                $upload->update([
                    'status' => 'failed',
                    'error_message' => "Invalid file. Column count must be {$expectedColumns}.",
                ]);
                return;
            }

            $rowNumber = 1;
            $maxErrors = 1000;
            $errorRows = [];
            $fileWaybills = [];

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
                //while (count($row) > $this->expectedColumns) {
                while (count($row) > $expectedColumns){
                    $row[$addressIndex] = ($row[$addressIndex] ?? '') . ' ' . array_pop($row);
                }

                //while (count($row) < $this->expectedColumns) {
                while (count($row) < $expectedColumns){
                    $row[] = '';
                }

                // Column count check
                //if (count($row) !== $this->expectedColumns) {
                if (count($row) !== $expectedColumns){
                    if (count($errorRows) < $maxErrors) {
                        $errorRows[] = "Row {$rowNumber}: Invalid column count";
                    }
                    continue;
                }

                // Date validation
                if (empty($row[1])) {
                    if (count($errorRows) < $maxErrors) {
                        $errorRows[] = "Row {$rowNumber}: Empty outbound date";
                    }
                    continue;
                }

                try {
                    Carbon::parse($row[1]);
                } catch (\Exception $e) {
                    if (count($errorRows) < $maxErrors) {
                        $errorRows[] = "Row {$rowNumber}: Invalid date format";
                    }
                    continue;
                }

                // Waybill validation
                $waybill = strtoupper(trim($row[9] ?? ''));
                if (empty($waybill)) {
                    if (count($errorRows) < $maxErrors) {
                        $errorRows[] = "Row {$rowNumber}: Waybill empty";
                    }
                    continue;
                }

                // Weight validation
                $weight = (float)($row[23] ?? 0);
                if ($weight <= 0) {
                    if (count($errorRows) < $maxErrors) {
                        $errorRows[] = "Row {$rowNumber}: Weight cannot be 0 or empty";
                    }
                    continue;
                }

                //$fileWaybills[$waybill] = true;
                $accountingDate = match ($this->category) {
                    'sender-prepaid' => !empty($row[1])
                        ? date('Y-m-d', strtotime($row[1]))
                        : null,

                    'sender-postpaid', 'receiver-postpaid' => !empty($row[31])
                        ? date('Y-m-d', strtotime($row[31]))
                        : null,

                    default => null,
                };

                // Duplicate in file
                $duplicateKey =
                    $waybill .
                    '|' .
                    ($accountingDate ?? 'NULL');

                if (isset($fileWaybills[$duplicateKey])) {

                    $firstRow = $fileWaybills[$duplicateKey]['row'];

                    if (count($errorRows) < $maxErrors) {

                        $errorRows[] =
                            "Row {$rowNumber}: Duplicate waybill in file ({$waybill}) first found at row {$firstRow}";
                    }

                    continue;
                }


                $fileWaybills[$duplicateKey] = [
                    'row' => $rowNumber,
                    'accounting_date' => $accountingDate,
                ];

                $customErrors = $this->validateCategoryRules(
                    $row,
                    $rowNumber,
                    $this->category
                );

                if (!empty($customErrors)) {

                    foreach ($customErrors as $err) {

                        if (count($errorRows) < $maxErrors) {
                            $errorRows[] = $err;
                        }
                    }

                    continue;
                }

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
            // Validated → Dispatch Import
            // -------------------------
            $upload->update([
                'status' => 'validated',
            ]);

            ImportAllWaybillsFileJob::dispatch(
                $this->uploadId, 
                $this->filePath,
                $this->username,
                $this->category,
            )->onQueue('import');

        } catch (\Throwable $e) {

            Log::error('CheckAllWaybillsFileJob failed', [
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


    private function validateCategoryRules(array $row, int $rowNumber, string $category): array
    {
        $errors = [];
        $rule = $this->paymentRules[$category] ?? null;

        if (!$rule) {
            return ["Row {$rowNumber}: Invalid category type ({$category})"];
        }

        $paymentBy   = $this->normalize($row[20] ?? '');
        $paymentType = $this->normalize($row[21] ?? '');

        $expectedBy   = $this->normalize($rule['payment_by'] ?? '');
        $expectedType = $this->normalize($rule['payment_type'] ?? '');

        if ($paymentBy !== $expectedBy) {
            $errors[] = "Row {$rowNumber}: Payment By must be '{$rule['payment_by']}'";
        }

        if ($paymentType !== $expectedType) {
            $errors[] = "Row {$rowNumber}: Payment Type must be '{$rule['payment_type']}'";
        }

        $checks = $rule['checks'] ?? [];

        if (($checks['origin_branch_required'] ?? false) && empty(trim($row[11] ?? ''))) {
            $errors[] = "Row {$rowNumber}: Origin Branch cannot be empty";
        }

        if (($checks['destination_branch_required'] ?? false) && empty(trim($row[13] ?? ''))) {
            $errors[] = "Row {$rowNumber}: Destination Branch cannot be empty";
        }

        if (($checks['delivered_date_required'] ?? false) && empty(trim($row[31] ?? ''))) {
            $errors[] = "Row {$rowNumber}: Delivered Date cannot be empty";
        }

        if (($checks['express_income_required'] ?? false) && ((float)($row[24] ?? 0)) <= 0) {
            $errors[] = "Row {$rowNumber}: Express Income must be > 0";
        }

        if (($checks['cod_required'] ?? false)) {
            if ((float)($row[25] ?? 0) < 0) $errors[] = "Row {$rowNumber}: COD Total Amount must be >= 0"; //0,+
            if ((float)($row[26] ?? 0) <= 0) $errors[] = "Row {$rowNumber}: COD Express Income must be > 0"; // >0
            if ((float)($row[27] ?? 0) < 0) $errors[] = "Row {$rowNumber}: COD Income must be >= 0"; //0,+
            if ((float)($row[28] ?? 0)  === '') $errors[] = "Row {$rowNumber}: COD Payable must not be empty"; //0,+,-
        }

        return $errors;
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

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    private function getExpectedColumns(): int
    {
        //return $this->paymentRules[$this->category]['columns'] ?? 34;

        return match ($this->category) {
            'receiver-postpaid' => 33,
            'sender-prepaid'    => 33,
            'sender-postpaid'   => 33,

            default => 33,
        };
    }
}