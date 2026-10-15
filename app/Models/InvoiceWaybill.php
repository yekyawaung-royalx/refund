<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceWaybill extends Model
{
    protected $fillable = [
        'invoice_id',
        'waybill_no',
        'outbound_date',
        'from_city',
        'to_city',
        'weight',
        'express_income_amount',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'express_income_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}