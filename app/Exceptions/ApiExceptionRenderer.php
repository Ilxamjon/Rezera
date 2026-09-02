<?php

namespace App\Exceptions;

use App\Exceptions\Businesses\BusinessVerificationException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\Payments\PaymentException;
use App\Exceptions\Subscriptions\SubscriptionException;
use App\Exceptions\Reservations\ReservationRuleException;
use App\Exceptions\ReservationConflictException;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $throwable, Request $request): ?JsonResponse
    {
        if (! self::shouldRenderAsApi($request)) {
            return null;
        }

        if ($throwable instanceof ReservationRuleException) {
            $response = ApiResponse::error(
                message: $throwable->getMessage(),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: $throwable->errorCode,
            );

            $payload = $response->getData(true);
            $payload = array_merge($payload, $throwable->context);

            return response()->json($payload, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($throwable instanceof ReservationConflictException) {
            return ApiResponse::error(
                message: $throwable->getMessage(),
                status: Response::HTTP_CONFLICT,
                code: 'booking_conflict',
            );
        }

        if ($throwable instanceof PaymentException) {
            $status = match ($throwable->errorCode) {
                'payment_already_active' => Response::HTTP_CONFLICT,
                'payment_not_allowed', 'payment_invalid_state', 'payment_provider_disabled', 'payment_provider_not_implemented' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'payment_provider_error' => Response::HTTP_BAD_GATEWAY,
                'invalid_webhook_signature', 'payment_simulation_disabled' => Response::HTTP_FORBIDDEN,
                default => Response::HTTP_UNPROCESSABLE_ENTITY,
            };

            return ApiResponse::error(
                message: $throwable->getMessage(),
                status: $status,
                code: $throwable->errorCode,
            );
        }

        if ($throwable instanceof SubscriptionException) {
            $status = match ($throwable->errorCode) {
                'PLAN_LIMIT_REACHED' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'PLAN_FEATURE_RESTRICTED' => Response::HTTP_FORBIDDEN,
                'SUBSCRIPTION_NOT_ACTIVE', 'INVALID_SUBSCRIPTION_TRANSITION', 'PLAN_NOT_AVAILABLE', 'SUBSCRIPTION_PAYMENT_REQUIRED' => Response::HTTP_UNPROCESSABLE_ENTITY,
                default => Response::HTTP_UNPROCESSABLE_ENTITY,
            };

            $response = ApiResponse::error(
                message: $throwable->getMessage(),
                status: $status,
                code: $throwable->errorCode,
            );

            $payload = $response->getData(true);
            $payload = array_merge($payload, $throwable->context);

            return response()->json($payload, $status);
        }

        if ($throwable instanceof BusinessVerificationException) {
            $response = ApiResponse::error(
                message: $throwable->getMessage(),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: $throwable->errorCode,
            );

            $payload = $response->getData(true);
            $payload = array_merge($payload, $throwable->context);

            return response()->json($payload, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($throwable instanceof InvalidCredentialsException) {
            return ApiResponse::error(
                message: $throwable->getMessage(),
                status: Response::HTTP_UNAUTHORIZED,
                code: 'invalid_credentials',
            );
        }

        if ($throwable instanceof ValidationException) {
            return ApiResponse::error(
                message: __('validation.failed'),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: $throwable->errors(),
                code: 'validation_failed',
            );
        }

        if ($throwable instanceof AuthenticationException) {
            return ApiResponse::error(
                message: __('auth.unauthenticated'),
                status: Response::HTTP_UNAUTHORIZED,
                code: 'unauthenticated',
            );
        }

        if ($throwable instanceof AuthorizationException) {
            return ApiResponse::error(
                message: $throwable->getMessage() ?: __('auth.unauthorized'),
                status: Response::HTTP_FORBIDDEN,
                code: 'forbidden',
            );
        }

        if ($throwable instanceof ModelNotFoundException) {
            return ApiResponse::error(
                message: __('errors.not_found'),
                status: Response::HTTP_NOT_FOUND,
                code: 'not_found',
            );
        }

        if ($throwable instanceof NotFoundHttpException) {
            return ApiResponse::error(
                message: __('errors.route_not_found'),
                status: Response::HTTP_NOT_FOUND,
                code: 'route_not_found',
            );
        }

        if ($throwable instanceof TooManyRequestsHttpException) {
            return ApiResponse::error(
                message: __('errors.too_many_requests'),
                status: Response::HTTP_TOO_MANY_REQUESTS,
                code: 'too_many_requests',
            );
        }

        if ($throwable instanceof QueryException) {
            Log::error('Database query exception', [
                'sql_state' => $throwable->errorInfo[0] ?? null,
                'message' => $throwable->getMessage(),
            ]);

            if (self::isExclusionViolation($throwable)) {
                return ApiResponse::error(
                    message: __('errors.resource_not_available'),
                    status: Response::HTTP_CONFLICT,
                    code: 'resource_not_available',
                );
            }

            return ApiResponse::error(
                message: __('errors.database_error'),
                status: Response::HTTP_CONFLICT,
                code: 'database_error',
            );
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return ApiResponse::error(
                message: $throwable->getMessage() ?: __('errors.http_error'),
                status: $throwable->getStatusCode(),
                code: 'http_error',
            );
        }

        Log::error('Unhandled API exception', [
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
            'trace' => config('app.debug') ? $throwable->getTraceAsString() : null,
        ]);

        return ApiResponse::error(
            message: config('app.debug')
                ? $throwable->getMessage()
                : __('errors.server_error'),
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
            code: 'server_error',
        );
    }

    private static function shouldRenderAsApi(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private static function isExclusionViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23P01';
    }
}
