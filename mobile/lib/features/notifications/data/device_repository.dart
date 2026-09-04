import 'dart:io' show Platform;

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/device_storage.dart';

class DeviceRepository {
  DeviceRepository(this._api, this._storage);

  final ApiClient _api;
  final DeviceStorage _storage;

  Future<void> register({String? pushToken}) async {
    final deviceId = await _storage.localDeviceId();
    final response = await _api.post<Map<String, dynamic>>(
      '/me/devices',
      data: {
        'device_id': deviceId,
        'platform': _platform(),
        'app_version': AppConfig.appVersion,
        'locale': _apiLocale(),
        if (pushToken != null && pushToken.isNotEmpty) 'push_token': pushToken,
      },
    );
    final data = response.data?['data'];
    final id = data is Map ? data['id'] : null;
    if (id is String && id.isNotEmpty) {
      await _storage.writeServerDeviceId(id);
    }
  }

  Future<void> deactivate() async {
    final serverId = await _storage.readServerDeviceId();
    if (serverId == null || serverId.isEmpty) return;
    try {
      await _api.delete<Map<String, dynamic>>('/me/devices/$serverId');
    } finally {
      await _storage.clearServerDeviceId();
    }
  }

  String _platform() {
    try {
      if (Platform.isIOS) return 'ios';
      if (Platform.isAndroid) return 'android';
    } catch (_) {}
    return 'android';
  }

  String _apiLocale() {
    final code = Intl.getCurrentLocale().split(RegExp('[_-]')).first.toLowerCase();
    if (code == 'uz' || code == 'kaa' || code == 'ru') return code;
    return 'ru';
  }
}

final deviceRepositoryProvider = Provider<DeviceRepository>((ref) {
  return DeviceRepository(
    ref.watch(apiClientProvider),
    ref.watch(deviceStorageProvider),
  );
});
