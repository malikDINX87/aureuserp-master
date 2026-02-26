<?php

namespace Webkul\DinxErpSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Partner\Models\Partner;

class DinxSyncMapping extends Model
{
    protected $table = 'dinx_sync_mappings';

    protected $fillable = [
        'external_lead_id',
        'partner_id',
        'last_delivery_id',
        'last_synced_at',
        'metadata',
    ];

    protected $casts = [
        'metadata'       => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id')->withTrashed();
    }
}
