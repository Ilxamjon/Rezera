import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../config/app_config.dart';
import '../errors/app_failure.dart';
import '../storage/token_storage.dart';

typedef UnauthorizedHandler = Future<void> Function();

class ApiClient {
  ApiClient({
    required TokenStorage tokenStorage,
    UnauthorizedHandler? onUnauthorized,
    Dio? dio,
  })  : _tokenStorage = tokenStorage,
        _onUnauthorized = onUnauthorized,
        _dio = dio ??
            Dio(
              BaseOptions(
                baseUrl: AppConfig.apiRoot,
                connectTimeout: const Duration(seconds: 8),
                receiveTimeout: const Duration(seconds: 15),
                sendTimeout: const Duration(seconds: 15),
                headers: {
                  'Accept': 'application/json',
                  'Content-Type': 'application/json',
                },
              ),
            ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.readToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          final locale = Intl.getCurrentLocale();
          options.headers['Accept-Language'] =
              locale.split(RegExp('[_-]')).first;
          handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401 && _onUnauthorized != null) {
            await _onUnauthorized();
          }
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;
  final TokenStorage _tokenStorage;
  final UnauthorizedHandler? _onUnauthorized;

  Dio get raw => _dio;

  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) {
    return _guard(() => _dio.get<T>(path, queryParameters: queryParameters));
  }

  Future<Response<T>> post<T>(
    String path, {
    Object? data,
    Map<String, dynamic>? queryParameters,
    Map<String, dynamic>? headers,
  }) {
    return _guard(
      () => _dio.post<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: headers == null ? null : Options(headers: headers),
      ),
    );
  }

  Future<Response<T>> put<T>(String path, {Object? data}) {
    return _guard(() => _dio.put<T>(path, data: data));
  }

  Future<Response<T>> patch<T>(String path, {Object? data}) {
    return _guard(() => _dio.patch<T>(path, data: data));
  }

  Future<Response<T>> delete<T>(String path) {
    return _guard(() => _dio.delete<T>(path));
  }

  Future<Response<T>> _guard<T>(Future<Response<T>> Function() run) async {
    try {
      return await run();
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }
}

AppFailure mapDioException(DioException e) {
  if (e.type == DioExceptionType.connectionTimeout ||
      e.type == DioExceptionType.receiveTimeout ||
      e.type == DioExceptionType.sendTimeout ||
      e.type == DioExceptionType.connectionError) {
    return const NetworkFailure();
  }

  final status = e.response?.statusCode;
  final body = e.response?.data;
  final message = _extractMessage(body);
  final code = body is Map ? body['code'] as String? : null;
  final errors = body is Map && body['errors'] is Map
      ? Map<String, dynamic>.from(body['errors'] as Map)
      : null;

  if (status == 401) {
    // Prefer human message from API (e.g. wrong password), not "sign in again".
    if (code == 'invalid_credentials' ||
        (message != null && message.isNotEmpty)) {
      return UnauthorizedFailure(message ?? 'error_unauthorized');
    }
    return const UnauthorizedFailure();
  }
  if (status == 429) {
    return UnknownFailure(message ?? 'error_rate_limited', code);
  }
  if (status == 409) {
    return ConflictFailure(message ?? 'error_conflict', code);
  }
  if (status == 422) {
    return ValidationFailure(message ?? 'error_validation', details: errors);
  }

  return UnknownFailure(message ?? 'error_unknown', code);
}

String? _extractMessage(dynamic body) {
  if (body is Map && body['message'] is String) {
    return body['message'] as String;
  }
  return null;
}

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(tokenStorageProvider);
  return ApiClient(tokenStorage: storage);
});
