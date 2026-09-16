import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

class DeviceStorage {
  DeviceStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const _deviceKey = 'rezera_device_id';
  static const _serverDeviceKey = 'rezera_server_device_id';
  static const _pushTokenKey = 'rezera_push_token';

  final FlutterSecureStorage _storage;
  final _uuid = const Uuid();

  Future<String> localDeviceId() async {
    final existing = await _storage.read(key: _deviceKey);
    if (existing != null && existing.isNotEmpty) {
      return existing;
    }
    final created = _uuid.v4();
    await _storage.write(key: _deviceKey, value: created);
    return created;
  }

  Future<String?> readServerDeviceId() => _storage.read(key: _serverDeviceKey);

  Future<void> writeServerDeviceId(String id) =>
      _storage.write(key: _serverDeviceKey, value: id);

  Future<void> clearServerDeviceId() => _storage.delete(key: _serverDeviceKey);

  Future<String?> readPushToken() => _storage.read(key: _pushTokenKey);

  Future<void> writePushToken(String token) =>
      _storage.write(key: _pushTokenKey, value: token);

  Future<void> clearPushToken() => _storage.delete(key: _pushTokenKey);
}

final deviceStorageProvider = Provider<DeviceStorage>((ref) => DeviceStorage());
