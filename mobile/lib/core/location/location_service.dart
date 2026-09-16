import 'package:geolocator/geolocator.dart';

class DeviceLocation {
  const DeviceLocation({required this.latitude, required this.longitude});

  final double latitude;
  final double longitude;
}

enum LocationFailureReason {
  serviceDisabled,
  denied,
  deniedForever,
  unavailable,
}

class LocationLookupResult {
  const LocationLookupResult.ok(this.location) : failure = null;

  const LocationLookupResult.fail(this.failure) : location = null;

  final DeviceLocation? location;
  final LocationFailureReason? failure;

  bool get isOk => location != null;
}

class LocationService {
  /// Resolves current (or last-known) coordinates with a clear failure reason.
  Future<LocationLookupResult> resolve() async {
    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      if (!enabled) {
        return const LocationLookupResult.fail(
          LocationFailureReason.serviceDisabled,
        );
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied) {
        return const LocationLookupResult.fail(LocationFailureReason.denied);
      }
      if (permission == LocationPermission.deniedForever) {
        return const LocationLookupResult.fail(
          LocationFailureReason.deniedForever,
        );
      }

      try {
        final position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.low,
            timeLimit: Duration(seconds: 12),
          ),
        );
        return LocationLookupResult.ok(
          DeviceLocation(
            latitude: position.latitude,
            longitude: position.longitude,
          ),
        );
      } catch (_) {
        final last = await Geolocator.getLastKnownPosition();
        if (last != null) {
          return LocationLookupResult.ok(
            DeviceLocation(latitude: last.latitude, longitude: last.longitude),
          );
        }
        return const LocationLookupResult.fail(
          LocationFailureReason.unavailable,
        );
      }
    } catch (_) {
      return const LocationLookupResult.fail(
        LocationFailureReason.unavailable,
      );
    }
  }

  /// Backwards-compatible helper used by other callers.
  Future<DeviceLocation?> currentOrNull() async {
    final result = await resolve();
    return result.location;
  }

  Future<bool> openLocationSettings() => Geolocator.openLocationSettings();

  Future<bool> openAppSettings() => Geolocator.openAppSettings();
}
