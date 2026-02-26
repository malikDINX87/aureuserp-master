<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Account\Models\Account;
use Webkul\Security\Models\User;

class DinxExpenseCategoryRule extends Model
{
    protected $table = 'dinx_expense_category_rules';

    protected $fillable = [
        'name',
        'match_pattern',
        'account_id',
        'confidence_boost',
        'is_active',
        'last_used_at',
        'created_by',
    ];

    protected $casts = [
        'confidence_boost' => 'decimal:2',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
