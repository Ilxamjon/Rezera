<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\ProcessPaymentWebhookAction;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Exceptions\Payments\PaymentException;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function handle(
        Request $request,
        string $provider,
        ProcessPaymentWebhookAction $processWebhook,
    ): JsonResponse {
        $paymentProvider = PaymentProvider::tryFrom($provider);

        if ($paymentProvider === null) {
            return ApiResponse::error(
                message: __('payments.provider_not_implemented', ['provider' => $provider]),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: 'payment_provider_not_implemented',
            );
        }

        try {
            $result = $processWebhook->execute($paymentProvider, $request);

            return ApiResponse::success([
                'processed' => $result['processed'],
                'payment_id' => $result['payment_id'],
            ]);
        } catch (PaymentException $exception) {
            $status = match ($exception->errorCode) {
                'payment_provider_not_implemented' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'invalid_webhook_signature', 'payment_simulation_disabled' => Response::HTTP_FORBIDDEN,
                default => Response::HTTP_UNPROCESSABLE_ENTITY,
            };

            return ApiResponse::error(
                message: $exception->getMessage(),
                status: $status,
                code: $exception->errorCode,
            );
        }
    }
}
