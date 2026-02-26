<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Security\Models\User;

class DinxReportFavorite extends Model
{
    protected $table = 'dinx_report_favorites';

    protected $fillable = [
        'user_id',
        'report_key',
        'label',
        'sort',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
