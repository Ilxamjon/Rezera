import 'dart:async';

import 'package:dio/dio.dart';

/// Retries transient network failures for safe/idempotent requests.
class RetryInterceptor extends Interceptor {
  RetryInterceptor({
    this.maxRetries = 2,
    this.retryableMethods = const {'GET', 'HEAD', 'OPTIONS'},
  });

  final int maxRetries;
  final Set<String> retryableMethods;

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final attempt = err.requestOptions.extra['retry_attempt'] as int? ?? 0;
    final method = err.requestOptions.method.toUpperCase();

    if (attempt >= maxRetries ||
        !retryableMethods.contains(method) ||
        !_shouldRetry(err)) {
      handler.next(err);
      return;
    }

    err.requestOptions.extra['retry_attempt'] = attempt + 1;
    await Future<void>.delayed(Duration(milliseconds: 300 * (attempt + 1)));

    try {
      final response = await Dio(
        BaseOptions(
          baseUrl: err.requestOptions.baseUrl,
          connectTimeout: err.requestOptions.connectTimeout,
          receiveTimeout: err.requestOptions.receiveTimeout,
          sendTimeout: err.requestOptions.sendTimeout,
          headers: err.requestOptions.headers,
        ),
      ).fetch(err.requestOptions);
      handler.resolve(response);
    } on DioException catch (e) {
      handler.next(e);
    }
  }

  bool _shouldRetry(DioException err) {
    if (err.type == DioExceptionType.connectionTimeout ||
        err.type == DioExceptionType.receiveTimeout ||
        err.type == DioExceptionType.sendTimeout ||
        err.type == DioExceptionType.connectionError) {
      return true;
    }
    final status = err.response?.statusCode;
    return status == 408 || status == 429 || (status != null && status >= 500);
  }
}
