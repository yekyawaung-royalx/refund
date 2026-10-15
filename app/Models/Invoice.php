<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'due_date',
        'customer_ref_no',
        'ref_no',
        'date_from',
        'date_to',
        'payment_term',
        'description',
        'attention',
        'contact_no',
        'express_amount',
        'untaxed_amount',
        'tax_amount',
        'tax',
        'currency',
        'approved_by',
        'total_amount',
        'total_amount_words',
        'waybill_details',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'date_from' => 'date',
        'date_to' => 'date',

        'express_amount' => 'decimal:2',
        'untaxed_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',

        'waybill_details' => 'boolean',
    ];

    public function waybills()
    {
        return $this->hasMany(InvoiceWaybill::class);
    }
}