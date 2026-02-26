<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Account\Models\Payment;

class DinxContractPaymentLink extends Model
{
    protected $table = 'dinx_contract_payment_links';

    protected $fillable = [
        'contract_id',
        'payment_id',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(DinxContract::class, 'contract_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
