<?php

namespace Webkul\DinxErpSync\Http\Controllers\API\V1;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Throwable;
use Webkul\DinxErpSync\Http\Requests\ClientConvertedWebhookRequest;
use Webkul\DinxErpSync\Jobs\ProcessClientConvertedWebhook;
use Webkul\DinxErpSync\Models\DinxSyncLog;
use Webkul\DinxErpSync\Services\DinxWebhookSignatureVerifier;

class ClientConvertedWebhookController extends BaseController
{
    public function __construct(protected DinxWebhookSignatureVerifier $signatureVerifier)
    {
    }

    public function __invoke(ClientConvertedWebhookRequest $request): JsonResponse
    {
        if (! $this->signatureVerifier->isEnabled()) {
            return response()->json([
                'message' => 'DINX ERP sync is disabled',
            ], 202);
        }

        $verification = $this->signatureVerifier->verify($request);

        if (! $verification['valid']) {
            return response()->json([
                'message' => $verification['message'],
            ], 401);
        }

        $deliveryId = trim((string) $request->header('X-DINX-Delivery-Id', ''));

        if ($deliveryId === '') {
            return response()->json([
                'message' => 'Missing X-DINX-Delivery-Id header',
            ], 422);
        }

        $existing = DinxSyncLog::query()
            ->where('delivery_id', $deliveryId)
            ->first();

        if ($existing) {
            return $this->respondForExistingDelivery($existing);
        }

        $payload = $request->validated();

        try {
            $syncLog = DinxSyncLog::query()->create([
                'delivery_id'      => $deliveryId,
                'event'            => (string) $payload['event'],
                'status'           => 'received',
                'external_lead_id' => data_get($payload, 'lead.id'),
                'payload'          => $payload,
                'headers'          => [
                    'x_dinx_delivery_id' => $deliveryId,
                    'x_dinx_timestamp'   => (string) $request->header('X-DINX-Timestamp', ''),
                    'x_dinx_signature'   => (string) $request->header('X-DINX-Signature', ''),
                ],
                'occurred_at' => data_get($payload, 'occurredAt'),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = DinxSyncLog::query()
                ->where('delivery_id', $deliveryId)
                ->first();

            if ($existing) {
                return $this->respondForExistingDelivery($existing);
            }

            throw $exception;
        }

        return $this->queueSyncLog($syncLog);
    }

    protected function respondForExistingDelivery(DinxSyncLog $syncLog): JsonResponse
    {
        if ($syncLog->status === 'failed') {
            $syncLog->forceFill([
                'status'        => 'received',
                'error_message' => null,
                'processed_at'  => null,
            ])->save();

            return $this->queueSyncLog($syncLog);
        }

        return response()->json([
            'message'    => $syncLog->status === 'processed'
                ? 'Delivery already processed'
                : 'Delivery already accepted',
            'deliveryId' => $syncLog->delivery_id,
            'status'     => $syncLog->status,
        ], 200);
    }

    protected function queueSyncLog(DinxSyncLog $syncLog): JsonResponse
    {
        try {
            $pendingDispatch = ProcessClientConvertedWebhook::dispatch($syncLog->id);

            $queue = $this->signatureVerifier->resolveProcessingQueue();
            if ($queue) {
                $pendingDispatch->onQueue($queue);
            }

            $syncLog->refresh();

            if ($syncLog->status === 'received') {
                $syncLog->forceFill([
                    'status' => 'queued',
                ])->save();
            }
        } catch (Throwable $exception) {
            report($exception);

            $syncLog->forceFill([
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at'  => now(),
            ])->save();

            return response()->json([
                'message' => 'Failed to queue webhook for processing',
            ], 500);
        }

        return response()->json([
            'message'    => 'Webhook accepted',
            'deliveryId' => $syncLog->delivery_id,
        ], 202);
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = data_get($exception->errorInfo, 0);

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
