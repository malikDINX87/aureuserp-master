<?php

namespace Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Webkul\DinxCommerce\Models\DinxContractEvent;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource;

class ViewDinxContract extends ViewRecord
{
    protected static string $resource = DinxContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_to_docusign')
                ->label('Send to DocuSign')
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    TextInput::make('envelope_id')
                        ->label('Envelope ID')
                        ->placeholder('Optional manual envelope ID'),
                ])
                ->action(function (array $data): void {
                    $envelopeId = trim((string) ($data['envelope_id'] ?? ''));
                    $record = $this->getRecord();

                    $record->forceFill([
                        'status' => 'sent',
                        'docusign_envelope_id' => $envelopeId !== '' ? $envelopeId : $record->docusign_envelope_id,
                    ])->save();

                    DinxContractEvent::query()->create([
                        'contract_id' => $record->id,
                        'provider' => 'docusign',
                        'event_type' => 'sent',
                        'provider_event_id' => $record->docusign_envelope_id,
                        'status' => 'sent',
                        'payload' => ['manual' => true],
                        'occurred_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Contract sent state updated')
                        ->success()
                        ->send();
                }),
        ];
    }
}
