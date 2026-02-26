<?php

namespace Webkul\DinxErpSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Partner\Models\Partner;

class DinxSyncLog extends Model
{
    protected $table = 'dinx_sync_logs';

    protected $fillable = [
        'delivery_id',
        'event',
        'status',
        'external_lead_id',
        'partner_id',
        'payload',
        'headers',
        'error_message',
        'occurred_at',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'headers'      => 'array',
        'occurred_at'  => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id')->withTrashed();
    }
}
