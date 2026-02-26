<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Account\Models\MoveLine;
use Webkul\Security\Models\User;

class DinxBankReconciliationMatch extends Model
{
    protected $table = 'dinx_bank_reconciliation_matches';

    protected $fillable = [
        'statement_line_id',
        'move_line_id',
        'score',
        'status',
        'reason',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(DinxBankStatementLine::class, 'statement_line_id');
    }

    public function moveLine(): BelongsTo
    {
        return $this->belongsTo(MoveLine::class, 'move_line_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withTrashed();
    }
}
