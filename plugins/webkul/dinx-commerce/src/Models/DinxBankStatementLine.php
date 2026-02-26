<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Account\Models\Account;
use Webkul\Account\Models\MoveLine;

class DinxBankStatementLine extends Model
{
    protected $table = 'dinx_bank_statement_lines';

    protected $fillable = [
        'import_id',
        'line_number',
        'transaction_date',
        'description',
        'reference',
        'amount',
        'balance',
        'currency',
        'suggested_account_id',
        'matched_move_line_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:4',
        'balance' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(DinxBankStatementImport::class, 'import_id');
    }

    public function suggestedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'suggested_account_id');
    }

    public function matchedMoveLine(): BelongsTo
    {
        return $this->belongsTo(MoveLine::class, 'matched_move_line_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(DinxBankReconciliationMatch::class, 'statement_line_id');
    }
}
