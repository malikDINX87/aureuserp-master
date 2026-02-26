<?php

namespace Webkul\DinxCommerce\Http\Controllers\API\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Throwable;
use Webkul\DinxCommerce\Services\DocuSignService;

class DocuSignWebhookController extends BaseController
{
    public function __construct(protected DocuSignService $docuSignService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->docuSignService->verifyWebhook($request)) {
            return response()->json([
                'message' => 'Invalid DocuSign webhook signature.',
            ], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Invalid webhook payload.',
            ], 422);
        }

        try {
            $result = $this->docuSignService->handleWebhookEvent($payload);

            return response()->json([
                'message' => (string) ($result['message'] ?? 'Webhook accepted.'),
                'handled' => (bool) ($result['handled'] ?? false),
            ], 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Failed to process DocuSign webhook.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
