<?php

namespace App\Actions\Payments;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\WebhookEventStatus;
use App\Exceptions\Payments\PaymentException;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaymentProviderResolver;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProcessPaymentWebhookAction
{
    public function __construct(
        private readonly PaymentProviderResolver $providerResolver,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * @return array{processed: bool, payment_id: ?string}
     */
    public function execute(PaymentProvider $provider, Request $request): array
    {
        $this->assertWebhookAuthorized($provider, $request);

        $payload = $request->all();
        $eventId = (string) ($payload['event_id'] ?? $request->header('X-Webhook-Event-Id', ''));

        if ($eventId === '') {
            $eventId = hash('sha256', json_encode($payload));
        }

        $existing = PaymentWebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        if ($existing !== null && $existing->status === WebhookEventStatus::Processed) {
            return ['processed' => true, 'payment_id' => $existing->payment_id];
        }

        return DB::transaction(function () use ($provider, $request, $payload, $eventId, $existing): array {
            $event = $existing ?? PaymentWebhookEvent::query()->create([
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => (string) ($payload['event_type'] ?? 'payment.status'),
                'payload' => $this->sanitizePayload($payload),
                'signature' => $request->header('X-Webhook-Signature'),
                'status' => WebhookEventStatus::Pending,
            ]);

            if ($event->status === WebhookEventStatus::Processed) {
                return ['processed' => true, 'payment_id' => $event->payment_id];
            }

            try {
                $gateway = $this->providerResolver->resolve($provider);
                $result = $gateway->handleCallback($payload);

                $payment = Payment::query()
                    ->where('provider', $provider)
                    ->where('provider_payment_id', $result->providerPaymentId)
                    ->first();

                if ($payment === null) {
                    throw PaymentException::invalidState(__('payments.payment_not_found_for_webhook'));
                }

                $updated = $this->paymentService->applyGatewayResult($payment, $result);

                $event->update([
                    'payment_id' => $updated->id,
                    'status' => WebhookEventStatus::Processed,
                    'processed_at' => now(),
                    'error_message' => null,
                ]);

                return ['processed' => true, 'payment_id' => $updated->id];
            } catch (\Throwable $exception) {
                Log::warning('payment.webhook.failed', [
                    'provider' => $provider->value,
                    'event_id' => $eventId,
                    'message' => $exception->getMessage(),
                ]);

                $event->update([
                    'status' => WebhookEventStatus::Failed,
                    'processed_at' => now(),
                    'error_message' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        });
    }

    private function assertWebhookAuthorized(PaymentProvider $provider, Request $request): void
    {
        if ($provider === PaymentProvider::Mock) {
            if (! config('payment.providers.mock.allow_simulation')) {
                throw PaymentException::simulationDisabled();
            }

            $secret = (string) config('payment.providers.mock.webhook_secret');
            $provided = (string) $request->header('X-Mock-Webhook-Secret', '');

            if ($secret !== '' && ! hash_equals($secret, $provided)) {
                throw PaymentException::invalidWebhookSignature();
            }

            return;
        }

        throw PaymentException::providerNotImplemented($provider->value);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = $payload;
        unset($sanitized['card_number'], $sanitized['cvv'], $sanitized['secret'], $sanitized['token']);

        return $sanitized;
    }
}
