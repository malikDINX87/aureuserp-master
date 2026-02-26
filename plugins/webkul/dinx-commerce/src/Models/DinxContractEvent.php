<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DinxContractEvent extends Model
{
    protected $table = 'dinx_contract_events';

    protected $fillable = [
        'contract_id',
        'provider',
        'event_type',
        'provider_event_id',
        'status',
        'payload',
        'message',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(DinxContract::class, 'contract_id');
    }
}
