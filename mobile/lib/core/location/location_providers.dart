import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../location/location_service.dart';

final locationServiceProvider = Provider<LocationService>((ref) {
  return LocationService();
});

final deviceLocationProvider =
    FutureProvider.autoDispose<DeviceLocation?>((ref) {
  return ref.watch(locationServiceProvider).currentOrNull();
});
