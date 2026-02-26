<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Security\Models\User;

class DinxBankStatementImport extends Model
{
    protected $table = 'dinx_bank_statement_imports';

    protected $fillable = [
        'file_name',
        'uploaded_by',
        'status',
        'statement_start_date',
        'statement_end_date',
        'total_lines',
        'matched_lines',
        'metadata',
    ];

    protected $casts = [
        'statement_start_date' => 'date',
        'statement_end_date' => 'date',
        'metadata' => 'array',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DinxBankStatementLine::class, 'import_id');
    }
}
