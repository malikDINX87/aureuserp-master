<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Account\Models\Invoice;

class DinxContractInvoiceLink extends Model
{
    protected $table = 'dinx_contract_invoice_links';

    protected $fillable = [
        'contract_id',
        'invoice_id',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(DinxContract::class, 'contract_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
