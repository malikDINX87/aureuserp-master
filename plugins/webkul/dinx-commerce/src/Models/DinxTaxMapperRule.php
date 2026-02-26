<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Account\Models\Tax;
use Webkul\Security\Models\User;

class DinxTaxMapperRule extends Model
{
    protected $table = 'dinx_tax_mapper_rules';

    protected $fillable = [
        'name',
        'match_pattern',
        'tax_id',
        'rate_override',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'rate_override' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
