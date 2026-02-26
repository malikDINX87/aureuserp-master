<?php

namespace Webkul\DinxCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Account\Models\Invoice;
use Webkul\Project\Models\Project;

class DinxProjectInvoiceLink extends Model
{
    protected $table = 'dinx_project_invoice_links';

    protected $fillable = [
        'project_id',
        'invoice_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
