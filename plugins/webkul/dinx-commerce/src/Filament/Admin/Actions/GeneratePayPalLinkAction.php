<?php

namespace Webkul\DinxCommerce\Filament\Admin\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;
use Webkul\Account\Enums\MoveState;
use Webkul\Account\Enums\PaymentState;
use Webkul\Account\Models\Move;
use Webkul\DinxCommerce\Models\DinxContractInvoiceLink;
use Webkul\DinxCommerce\Services\PayPalService;

class GeneratePayPalLinkAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'dinx.generate_paypal_link';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Generate PayPal Link')
            ->icon('heroicon-o-link')
            ->color('warning')
            ->visible(function (Move $record): bool {
                return $record->state === MoveState::POSTED
                    && in_array($record->payment_state, [
                        PaymentState::NOT_PAID,
                        PaymentState::PARTIAL,
                        PaymentState::IN_PAYMENT,
                    ], true);
            })
            ->action(function (Move $record): void {
                try {
                    $contractId = DinxContractInvoiceLink::query()
                        ->where('invoice_id', $record->id)
                        ->value('contract_id');

                    $url = app(PayPalService::class)->getOrCreateApprovalUrl($record, $contractId ? (int) $contractId : null);

                    Notification::make()
                        ->title('PayPal payment link generated')
                        ->body($url)
                        ->success()
                        ->persistent()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('PayPal link generation failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
