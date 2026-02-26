<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Account\Models\Invoice;

class DinxPayPalOrder extends Model
{
    protected $table = 'dinx_paypal_orders';

    protected $fillable = [
        'invoice_id',
        'contract_id',
        'paypal_order_id',
        'paypal_capture_id',
        'status',
        'amount',
        'currency',
        'approval_url',
        'raw_payload',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'raw_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(DinxContract::class, 'contract_id');
    }
}
