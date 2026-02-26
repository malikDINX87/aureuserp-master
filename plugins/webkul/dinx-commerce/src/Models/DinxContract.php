<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Account\Models\Invoice;
use Webkul\Account\Models\Payment;
use Webkul\Partner\Models\Partner;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Currency;

class DinxContract extends Model
{
    protected $table = 'dinx_contracts';

    protected $fillable = [
        'partner_id',
        'title',
        'status',
        'currency_id',
        'amount_total',
        'effective_date',
        'expiration_date',
        'terms_html',
        'signed_at',
        'docusign_envelope_id',
        'signed_document_path',
        'created_by',
    ];

    protected $casts = [
        'amount_total' => 'decimal:4',
        'effective_date' => 'date',
        'expiration_date' => 'date',
        'signed_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id')->withTrashed();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function invoiceLinks(): HasMany
    {
        return $this->hasMany(DinxContractInvoiceLink::class, 'contract_id');
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(DinxContractPaymentLink::class, 'contract_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DinxContractEvent::class, 'contract_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DinxContractVersion::class, 'contract_id');
    }

    public function paypalOrders(): HasMany
    {
        return $this->hasMany(DinxPayPalOrder::class, 'contract_id');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'dinx_contract_invoice_links', 'contract_id', 'invoice_id');
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'dinx_contract_payment_links', 'contract_id', 'payment_id');
    }
}
