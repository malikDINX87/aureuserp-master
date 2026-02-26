<?php

namespace Webkul\DinxCommerce\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;
use Webkul\Account\Enums\MoveState;
use Webkul\Account\Enums\PaymentState;
use Webkul\Account\Facades\Account as AccountFacade;
use Webkul\Account\Models\Invoice;
use Webkul\DinxCommerce\Models\DinxRecurringInvoiceProfile;

class RecurringInvoiceService
{
    public function runDueProfiles(?Carbon $now = null): array
    {
        $now ??= now();

        $profiles = DinxRecurringInvoiceProfile::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->get();

        $processed = 0;
        $created = 0;
        $failed = 0;

        foreach ($profiles as $profile) {
            $processed++;

            try {
                $this->runProfile($profile, $now);
                $created++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'created' => $created,
            'failed' => $failed,
        ];
    }

    public function runProfile(DinxRecurringInvoiceProfile $profile, ?Carbon $runAt = null): ?Invoice
    {
        $runAt ??= now();

        $source = $profile->sourceInvoice;

        if (! $source || ! $source->exists) {
            $profile->forceFill([
                'is_active' => false,
                'metadata' => array_merge((array) $profile->metadata, [
                    'last_error' => 'Source invoice no longer exists.',
                ]),
            ])->save();

            return null;
        }

        $scheduledDate = Carbon::parse($profile->next_run_at ?? $runAt)->startOfDay();

        return DB::transaction(function () use ($profile, $source, $scheduledDate, $runAt) {
            $newInvoice = $source->replicate([
                'name',
                'reference',
                'state',
                'payment_state',
                'amount_untaxed',
                'amount_tax',
                'amount_total',
                'amount_residual',
                'amount_untaxed_signed',
                'amount_untaxed_in_currency_signed',
                'amount_tax_signed',
                'amount_total_signed',
                'amount_total_in_currency_signed',
                'amount_residual_signed',
                'inalterable_hash',
                'posted_before',
                'checked',
                'is_move_sent',
                'created_at',
                'updated_at',
            ]);

            $newInvoice->state = MoveState::DRAFT;
            $newInvoice->payment_state = PaymentState::NOT_PAID;
            $newInvoice->name = '/';
            $newInvoice->reference = null;
            $newInvoice->date = $scheduledDate;
            $newInvoice->invoice_date = $scheduledDate;
            $newInvoice->invoice_date_due = $scheduledDate->copy()->addDays(30);
            $newInvoice->is_move_sent = false;
            $newInvoice->checked = false;
            $newInvoice->posted_before = false;

            $newInvoice->save();

            foreach ($source->lines as $line) {
                $newLine = $line->replicate([
                    'move_id',
                    'reconcile_id',
                    'payment_id',
                    'full_reconcile_id',
                    'matching_number',
                    'reconciled',
                    'created_at',
                    'updated_at',
                ]);

                $newLine->move_id = $newInvoice->id;
                $newLine->parent_state = $newInvoice->state;
                $newLine->date = $newInvoice->date;
                $newLine->invoice_date = $newInvoice->invoice_date;
                $newLine->date_maturity = $newInvoice->invoice_date_due;
                $newLine->reconciled = false;
                $newLine->save();

                if ($line->taxes->isNotEmpty()) {
                    $newLine->taxes()->sync($line->taxes->pluck('id')->all());
                }
            }

            AccountFacade::computeAccountMove($newInvoice);

            if ($profile->auto_send && method_exists($newInvoice, 'sendAndPrint')) {
                try {
                    $newInvoice->sendAndPrint();
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            $profile->forceFill([
                'last_run_at' => $runAt,
                'next_run_at' => $this->nextRunAt($profile, $scheduledDate),
                'metadata' => array_merge((array) $profile->metadata, [
                    'last_invoice_id' => $newInvoice->id,
                    'last_invoice_created_at' => now()->toIso8601String(),
                ]),
            ])->save();

            return $newInvoice;
        });
    }

    public function nextRunAt(DinxRecurringInvoiceProfile $profile, ?Carbon $from = null): Carbon
    {
        $from ??= now();

        $next = $from->copy()->addMonthNoOverflow()->startOfDay();
        $day = max(1, min((int) $profile->day_of_month, 28));

        return $next->day($day);
    }
}
