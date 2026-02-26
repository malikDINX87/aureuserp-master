<?php

namespace Webkul\DinxErpSync\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Webkul\DinxErpSync\Models\DinxSyncLog;
use Webkul\DinxErpSync\Services\ClientConversionSyncService;

class ProcessClientConvertedWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120];

    public function __construct(public int $syncLogId)
    {
    }

    public function handle(ClientConversionSyncService $syncService): void
    {
        $syncLog = DinxSyncLog::query()->find($this->syncLogId);

        if (! $syncLog) {
            return;
        }

        if ($syncLog->status === 'processed') {
            return;
        }

        $syncLog->forceFill([
            'status'        => 'processing',
            'error_message' => null,
        ])->save();

        $syncService->process($syncLog);
    }

    public function failed(Throwable $exception): void
    {
        $syncLog = DinxSyncLog::query()->find($this->syncLogId);

        if (! $syncLog) {
            return;
        }

        $syncLog->forceFill([
            'status'        => 'failed',
            'error_message' => $exception->getMessage(),
            'processed_at'  => now(),
        ])->save();
    }
}
