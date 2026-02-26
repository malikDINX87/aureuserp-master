<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Security\Models\User;

class DinxContractVersion extends Model
{
    protected $table = 'dinx_contract_versions';

    protected $fillable = [
        'contract_id',
        'version_number',
        'label',
        'status',
        'terms_html',
        'snapshot',
        'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(DinxContract::class, 'contract_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
