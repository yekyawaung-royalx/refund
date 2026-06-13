<?php

namespace App\Jobs;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckRefundFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected string $username;
    protected int $uploadId;
    protected string $filePath;

    protected int $expectedColumns = 7;

    public function __construct(int $uploadId, string $filePath, string $username)
    {
        $this->uploadId = $uploadId;
        $this->filePath = $filePath;
        $this->username = $username;
    }

    public function handle()
    {
        $upload = Upload::find($this->uploadId);
        if (!$upload) return;

        $upload->update([
            'status' => 'checking',
            'attempts' => $this->attempts(),
        ]);

        $file = new \SplFileObject($this->filePath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(",");

        $headers = $file->fgetcsv();

        if (!$headers || count($headers) !== $this->expectedColumns) {

            $upload->update([
                'status' => 'failed',
                'error_message' => 'Invalid refund file.Column structure does not match or file type is incorrect.Column count (must be 7).'
            ]);

            return;
        }

        $rowNumber = 1;
        $errors = [];
        $fileWaybills = [];

        while (!$file->eof()) {

            $row = $file->fgetcsv();

            if ($row === false || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            $rowNumber++;

            if (count($row) !== $this->expectedColumns) {
                $errors[] = "Row {$rowNumber}: column count invalid";
                continue;
            }

            $paymentDate = $row[2];
            $waybillNo = strtoupper(trim($row[5] ?? ''));
            $amountErrors = $this->validateAmountFormat($row, $rowNumber);

            if (!empty($amountErrors)) {
                foreach ($amountErrors as $error) {
                    $errors[] = $error;
                }

                continue;
            }

            if (!$paymentDate || !strtotime($paymentDate)) {
                $errors[] = "Row {$rowNumber}: invalid payment date";
            }

            if (!$waybillNo) {
                $errors[] = "Row {$rowNumber}: waybill empty";
            }

            if (isset($fileWaybills[$waybillNo])) {
                $firstRow = $fileWaybills[$waybillNo];
                if (count($errors) < 1000) {
                    $errors[] =
                        "Row {$rowNumber}: Duplicate waybill in file ({$waybillNo}) first found at row {$firstRow}";
                }

                continue;
            }
            $fileWaybills[$waybillNo] = $rowNumber;
        }

        if (!empty($errors)) {

            $upload->update([
                'status' => 'failed',
                'error_message' => substr(implode("\n", $errors), 0, 65000)
            ]);

            return;
        }

        $upload->update([
            'status' => 'validated'
        ]);

        ImportRefundedToStagingJob::dispatch(
            $this->uploadId,
            $this->filePath,
            $this->username
        )->onQueue('import');
    }

    private function hasInvalidAmountFormat($value): bool
    {
        if ($value === null || trim((string)$value) === '') {
            return false;
        }

        return str_contains((string)$value, ',');
    }

    private function validateAmountFormat(array $row, int $rowNumber): array
    {
        $errors = [];

        // Change index if refund amount column is different
        $amountColumns = [
            0 => 'Refund Amount',
        ];

        foreach ($amountColumns as $index => $label) {

            $value = $row[$index] ?? null;

            if ($this->hasInvalidAmountFormat($value)) {
                $errors[] =
                    "Row {$rowNumber}: {$label} has invalid amount format (comma not allowed)";
            }
        }

        return $errors;
    }
}