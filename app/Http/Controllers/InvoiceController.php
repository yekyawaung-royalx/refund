<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;
use App\Jobs\GenerateInvoiceJournalJob;

class InvoiceController extends Controller
{
    public function invoices(){
        $invoices = DB::table('invoices')->orderBy('id','desc')->paginate(20);

        //return $invoices;
        return Inertia::render('invoices/Index', [
            'invoices' => $invoices
        ]);
    }

    public function invoice_generate(){
        $users = DB::table('users')->select('name','id')->orderBy('name')->get();

        return Inertia::render('invoices/Create', [
            'users' => $users
        ]);
    }

    public function view(string $invoice)
    {
        // Invoice information
        $invoiceData = DB::table('invoices')
            ->where('invoice_no', $invoice)
            ->first();

        abort_if(!$invoiceData, 404, 'Invoice not found.');

        // Waybills belonging to this invoice
        $waybills = DB::table('invoice_waybills')
            ->where('invoice_id', $invoiceData->id)
            ->orderBy('id')
            ->get([
                'id',
                'waybill_no',
                'created_at',
            ]);

        //return $invoiceData;
        return Inertia::render('invoices/View', [
            'invoice' => $invoiceData,
            'waybills' => $waybills,
        ]);
    }

    public function print($invoice)
    {
        // -------------------------------------------------
        // Invoice
        // -------------------------------------------------

        $invoiceData = DB::table('invoices')
            ->where('id', $invoice)
            ->first();

        if (!$invoiceData) {
            abort(404, 'Invoice not found.');
        }

        // -------------------------------------------------
        // Waybills
        // -------------------------------------------------

        $waybills = DB::table('invoice_waybills')
            ->where('invoice_id', $invoice)
            ->get();

        // -------------------------------------------------
        // Print View
        // -------------------------------------------------
        // echo "<pre>";
        // print_r($waybills);
        // echo "</pre>";
        // exit;
        return Inertia::render('invoices/Print', [
            'invoice' => $invoiceData,
            'waybills' => $waybills,
        ]);
    }
    

    public function search_customer(Request $request)
    {
        $search = trim($request->input('search', ''));
        $limit = min((int) $request->input('limit', 10), 10);

        if ($search === '') {
            return response()->json([
                'data' => [],
            ]);
        }

        $customers = DB::table('upload_data')
            ->select([
                'customer_reference_no',
                'customer',
            ])
            ->whereNotNull('customer_reference_no')
            ->where('customer_reference_no', '!=', '')
            ->where('customer_reference_no', 'like', "{$search}%")
            ->where(function ($query) {
                $query->whereNull('refund')
                    ->orWhere('refund', '!=', 3);
            })
            ->distinct()
            ->orderBy('customer')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $customers,
        ]);
    }

    public function generated_invoice(Request $request)
    {

        $validated = $request->validate([
            'invoice_date'       => ['required', 'date'],
            'due_date'           => ['nullable', 'date'],
            'customer_ref_no'    => ['required', 'string', 'max:100'],
            'ref_no'             => ['nullable', 'string', 'max:100'],
            'date_from'          => ['required', 'date'],
            'date_to'            => ['required', 'date', 'after_or_equal:date_from'],
            'payment_term'       => ['nullable', 'string', 'max:50'],
            'description'        => ['nullable', 'string'],
            'attention'          => ['nullable', 'string', 'max:255'],
            'contactNo'          => ['nullable', 'string', 'max:50'],
            'express_amount'     => ['nullable', 'numeric'],
            'untaxed_amount'     => ['nullable', 'numeric'],
            'tax_amount'         => ['nullable', 'numeric'],
            'tax'                => ['nullable', 'numeric'],
            'currency'           => ['nullable', 'string', 'max:10'],
            'approved_by'        => ['nullable', 'integer'],
            'total_amount'       => ['nullable', 'numeric'],
            'total_amount_words' => ['nullable', 'string'],
            'waybill_details'    => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($validated) {
            /*
            |--------------------------------------------------------------------------
            | 1. Find available waybills FIRST
            |--------------------------------------------------------------------------
            */

            $waybills = DB::table('upload_data')
                ->select([
                    'waybill_no',
                    'outbound_date',
                    'from_city',
                    'to_city',
                    'weight',
                    'express_income_amount',
                ])
                ->where('customer_reference_no', $validated['customer_ref_no'])
                ->whereBetween('outbound_date', [
                    $validated['date_from'],
                    $validated['date_to'],
                ])
                ->where(function ($query) {
                    $query->whereNull('refund')
                        ->orWhere('refund', '!=', 3);
                })
                ->whereNotNull('waybill_no')
                ->where('waybill_no', '!=', '')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 2. Stop if no waybill exists
            |--------------------------------------------------------------------------
            */

            if ($waybills->isEmpty()) {
                throw ValidationException::withMessages([
                    'customer_ref_no' =>
                        'No available waybills found for this customer within the selected date range.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Generate Invoice No
            |--------------------------------------------------------------------------
            */

            $invoiceCount = Invoice::whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])->count();

            $invoiceNo = str_pad(
                $invoiceCount + 1,
                6,
                '0',
                STR_PAD_LEFT
            );

            /*
            |--------------------------------------------------------------------------
            | 4. Create Invoice
            |--------------------------------------------------------------------------
            */
            $directory = 'exports/' . now()->format('Y-m');
            $safeCustomerRef = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '_',
                $validated['customer_ref_no']
            );

            $fileName = sprintf(
                'invoice-journal-%s-%s-%s-%s.xlsx',
                $safeCustomerRef,
                \Carbon\Carbon::parse($validated['date_from'])->toDateString(),
                \Carbon\Carbon::parse($validated['date_to'])->toDateString(),
                now()->format('Ymd_His')
            );

            // Relative path inside the local storage disk
            $filePath = "{$directory}/{$fileName}";

            $invoice = Invoice::create([
                'invoice_no'         => $invoiceNo,
                'invoice_date'       => $validated['invoice_date'],
                'due_date'           => $validated['due_date'] ?? null,
                'customer_ref_no'    => $validated['customer_ref_no'],
                'ref_no'             => $validated['ref_no'] ?? null,
                'date_from'          => $validated['date_from'],
                'date_to'            => $validated['date_to'],
                'payment_term'       => $validated['payment_term'] ?? null,
                'description'        => $validated['description'] ?? null,
                'attention'          => $validated['attention'] ?? null,
                'contact_no'         => $validated['contactNo'] ?? null,
                'express_amount'     => $validated['express_amount'] ?? 0,
                'untaxed_amount'     => $validated['untaxed_amount'] ?? 0,
                'tax_amount'         => $validated['tax_amount'] ?? 0,
                'tax'                => $validated['tax'] ?? 0,
                'currency'           => $validated['currency'] ?? 'MMK',
                'approved_by'        => $validated['approved_by'] ?? null,
                'total_amount'       => $validated['total_amount'] ?? 0,
                'total_amount_words' => $validated['total_amount_words'] ?? null,
                'waybill_details'    => $validated['waybill_details'] ?? false,
                'file_name'          => $filePath,   
            ]);

            /*
            |--------------------------------------------------------------------------
            | 5. Save Invoice Waybills
            |--------------------------------------------------------------------------
            */

            foreach ($waybills as $waybill) {
                $invoice->waybills()->create([
                    'waybill_no'             => $waybill->waybill_no,
                    'outbound_date'          => $waybill->outbound_date,
                    'from_city'              => $waybill->from_city,
                    'to_city'                => $waybill->to_city,
                    'weight'                 => $waybill->weight,
                    'express_income_amount'  => $waybill->express_income_amount,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Mark upload_data as invoiced
            |--------------------------------------------------------------------------
            */

            $waybillNos = $waybills
                ->pluck('waybill_no')
                ->unique()
                ->values();

            DB::table('upload_data')
                ->where('customer_reference_no', $validated['customer_ref_no'])
                ->whereBetween('outbound_date', [
                    $validated['date_from'],
                    $validated['date_to'],
                ])
                ->whereIn('waybill_no', $waybillNos)
                ->update([
                    'refund' => 3,
                ]);

            /*
            |--------------------------------------------------------------------------
            | 7. Call GenerateInvoiceJournalJob
            |--------------------------------------------------------------------------
            */
            GenerateInvoiceJournalJob::dispatch(
                customerRefNo: $validated['customer_ref_no'],
                dateFrom: $validated['date_from'],
                dateTo: $validated['date_to'],
                invoiceDate: $validated['invoice_date'],
                dueDate: $validated['due_date'] ?? null,
                analyticAccount: 'YGN',
                operatingUnit: 'PMO',
                waybillNos: $waybillNos->all(),
                filePath: $filePath,
            )->afterCommit();

            /*
            |--------------------------------------------------------------------------
            | 8. Response
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully.',
                'data' => [
                    'invoice_id'    => $invoice->id,
                    'invoice_no'    => $invoice->invoice_no,
                    'waybill_count' => $waybills->count(),
                ],
            ]);
        });
    }

    public function customerSummary(Request $request)
    {
        $validated = $request->validate([
            'customer_ref_no' => ['required', 'string'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $summary = DB::table('upload_data')
            ->where(
                'customer_reference_no',
                $validated['customer_ref_no']
            )
            ->whereBetween('accounting_date', [
                $validated['date_from'],
                $validated['date_to'],
            ])
            ->selectRaw('
                COUNT(*) AS waybills,
                COALESCE(
                    SUM(express_income_amount),
                    0
                ) AS express_income_amount
            ')
            ->first();

        return response()->json([
            'waybills' => (int) $summary->waybills,
            'express_income_amount' => (float) $summary->express_income_amount,
        ]);
    }

}
