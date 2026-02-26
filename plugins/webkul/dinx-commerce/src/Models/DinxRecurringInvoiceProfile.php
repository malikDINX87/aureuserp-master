<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Account\Models\Invoice;
use Webkul\Partner\Models\Partner;
use Webkul\Security\Models\User;

class DinxRecurringInvoiceProfile extends Model
{
    protected $table = 'dinx_recurring_invoice_profiles';

    protected $fillable = [
        'name',
        'source_invoice_id',
        'partner_id',
        'interval',
        'day_of_month',
        'next_run_at',
        'last_run_at',
        'auto_send',
        'allow_paypal',
        'is_active',
        'currency',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'auto_send' => 'boolean',
        'allow_paypal' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
