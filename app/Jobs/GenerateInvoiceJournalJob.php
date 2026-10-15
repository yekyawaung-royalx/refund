<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class GenerateInvoiceJournalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public string $customerRefNo,
        public string $dateFrom,
        public string $dateTo,
        public string $invoiceDate,
        public string $filePath,

        public ?string $dueDate = null,
        public string $analyticAccount = 'YGN',
        public string $operatingUnit = 'PMO',
        public ?array $waybillNos = null,
    ) {
    }

    public function handle(): void
    {
        $fromDate = Carbon::parse($this->dateFrom)->toDateString();
        $toDate = Carbon::parse($this->dateTo)->toDateString();

        $invoiceDate = Carbon::parse(
            $this->invoiceDate
        )->toDateString();

        $dueDate = $this->dueDate
            ? Carbon::parse($this->dueDate)->toDateString()
            : $invoiceDate;

        /*
        |--------------------------------------------------------------------------
        | 1. Query upload_data
        |--------------------------------------------------------------------------
        */

        $query = DB::table('upload_data')
            ->where(
                'customer_reference_no',
                $this->customerRefNo
            )
            ->whereBetween('accounting_date', [
                $fromDate,
                $toDate,
            ]);

        if ($this->waybillNos !== null) {
            if (empty($this->waybillNos)) {
                throw new RuntimeException(
                    'No waybills found for invoice journal.'
                );
            }

            $query->whereIn(
                'waybill_no',
                $this->waybillNos
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Calculate total amount
        |--------------------------------------------------------------------------
        */

        $totalAmount = (float) $query->sum(
            'express_income_amount'
        );

        if ($totalAmount <= 0) {
            throw new RuntimeException(
                'No positive express income amount found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Prepare journal rows
        |--------------------------------------------------------------------------
        */

        $period = Carbon::parse($invoiceDate)->format('F Y');

        $rows = [
            [
                '506011',
                $this->customerRefNo,
                $this->analyticAccount,
                $invoiceDate,
                $dueDate,
                $totalAmount,
                0,
                $this->operatingUnit,
                "{$invoiceDate} {$this->customerRefNo} Invoice Income for {$period}",
            ],
            [
                '272381',
                $this->customerRefNo,
                $this->analyticAccount,
                $invoiceDate,
                $dueDate,
                0,
                $totalAmount,
                $this->operatingUnit,
                "{$invoiceDate} {$this->customerRefNo} Invoice Receivable for {$period}",
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | 4. Create XLSX
        |--------------------------------------------------------------------------
        */

        $spreadsheet = new Spreadsheet();

        try {
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setTitle('Invoice Journal');

            $headers = [
                'Account',
                'Partner',
                'Analytic Account',
                'Date',
                'Due Date',
                'Debit',
                'Credit',
                'Operating Unit',
                'Label',
            ];

            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($rows, null, 'A2');

            $sheet->getStyle('A1:I1')
                ->getFont()
                ->setBold(true);

            $sheet->getStyle('A1:I1')
                ->getFill()
                ->setFillType(
                    \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID
                )
                ->getStartColor()
                ->setARGB('FFDCE6F1');

            $sheet->getStyle('F2:G3')
                ->getNumberFormat()
                ->setFormatCode('#,##0.00;[Red](#,##0.00);-');

            $sheet->getStyle('D2:E3')
                ->getNumberFormat()
                ->setFormatCode('yyyy-mm-dd');

            foreach (range('A', 'H') as $column) {
                $sheet->getColumnDimension($column)
                    ->setAutoSize(true);
            }

            $sheet->getColumnDimension('I')->setWidth(65);

            $sheet->getStyle('I2:I3')
                ->getAlignment()
                ->setWrapText(true);

            $sheet->freezePane('A2');

            /*
            |--------------------------------------------------------------------------
            | 5. Save XLSX file
            |--------------------------------------------------------------------------
            */

            $directory = dirname($this->filePath);

            Storage::disk('local')->makeDirectory($directory);

            $absolutePath = Storage::disk('local')->path(
                $this->filePath
            );

            (new Xlsx($spreadsheet))->save($absolutePath);

            logger()->info(
                'Invoice journal generated successfully.',
                [
                    'customer_ref_no' => $this->customerRefNo,
                    'date_from' => $fromDate,
                    'date_to' => $toDate,
                    'total_amount' => $totalAmount,
                    'file_path' => $relativePath,
                ]
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function failed(?Throwable $exception): void
    {
        logger()->error('Invoice journal generation failed.', [
            'customer_ref_no' => $this->customerRefNo,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'error' => $exception?->getMessage(),
        ]);
    }
}
