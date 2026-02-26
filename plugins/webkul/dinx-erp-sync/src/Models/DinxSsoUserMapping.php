<?php

namespace Webkul\DinxErpSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Security\Models\User;

class DinxSsoUserMapping extends Model
{
    protected $table = 'dinx_sso_user_mappings';

    protected $fillable = [
        'dinx_user_id',
        'user_id',
        'email',
        'last_role',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
