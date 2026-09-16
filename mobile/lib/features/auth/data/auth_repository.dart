import '../../../core/network/api_client.dart';
import 'auth_models.dart';

class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  Future<AuthTokenPayload> login({
    required String phone,
    required String password,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/auth/login',
      data: {
        'phone': phone,
        'password': password,
        'device_name': 'mobile',
      },
    );
    return AuthTokenPayload.fromJson(_unwrap(response.data));
  }

  Future<AuthTokenPayload> register({
    required String name,
    required String phone,
    required String password,
    required String passwordConfirmation,
    String locale = 'ru',
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/auth/register',
      data: {
        'name': name,
        'phone': phone,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'locale': locale,
        'device_name': 'mobile',
      },
    );
    return AuthTokenPayload.fromJson(_unwrap(response.data));
  }

  Future<OtpRequestResult> requestOtp({required String phone}) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/auth/otp/request',
      data: {'phone': phone, 'purpose': 'login'},
    );
    return OtpRequestResult.fromJson(_unwrap(response.data));
  }

  Future<AuthTokenPayload> verifyOtp({
    required String phone,
    required String code,
    String? name,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/auth/otp/verify',
      data: {
        'phone': phone,
        'code': code,
        'device_name': 'mobile',
        if (name != null && name.isNotEmpty) 'name': name,
      },
    );
    return AuthTokenPayload.fromJson(_unwrap(response.data));
  }

  Future<UserSession> me() async {
    final response = await _api.get<Map<String, dynamic>>('/auth/me');
    return UserSession.fromJson(_unwrap(response.data));
  }

  Future<void> logout() async {
    await _api.post<Map<String, dynamic>>('/auth/logout');
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? body) {
    if (body == null) {
      throw StateError('Empty API body');
    }
    final data = body['data'];
    if (data is Map<String, dynamic>) {
      return data;
    }
    if (data is Map) {
      return Map<String, dynamic>.from(data);
    }
    return body;
  }
}
