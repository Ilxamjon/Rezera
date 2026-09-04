import 'package:uuid/uuid.dart';

import '../../../core/network/api_client.dart';
import 'reservation_models.dart';

class ReservationRepository {
  ReservationRepository(this._api);

  final ApiClient _api;
  final _uuid = const Uuid();

  Future<List<AvailabilityResourceRow>> availability({
    required String businessId,
    required String date,
    required String startTime,
    required String endTime,
  }) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/businesses/$businessId/availability',
      queryParameters: {
        'date': date,
        'start_time': startTime,
        'end_time': endTime,
      },
    );
    final data = response.data?['data'];
    final resources = data is Map ? data['resources'] : null;
    if (resources is! List) return const [];
    return resources
        .whereType<Map>()
        .map(
          (e) => AvailabilityResourceRow.fromJson(
            Map<String, dynamic>.from(e),
          ),
        )
        .toList();
  }

  Future<ReservationSummary> create({
    required String businessId,
    required String resourceId,
    required String date,
    required String startTime,
    required String endTime,
    String? notes,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/businesses/$businessId/reservations',
      data: {
        'resource_id': resourceId,
        'date': date,
        'start_time': startTime,
        'end_time': endTime,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      },
      headers: {'Idempotency-Key': _uuid.v4()},
    );
    return ReservationSummary.fromJson(_unwrap(response.data));
  }

  Future<List<ReservationSummary>> mine({String? status}) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/me/reservations',
      queryParameters: {
        if (status != null) 'status': status,
        'per_page': 50,
      },
    );
    final data = response.data?['data'];
    final items = data is Map ? data['items'] : (data is List ? data : null);
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map((e) => ReservationSummary.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<void> cancel(String reservationId) async {
    await _api.post<Map<String, dynamic>>(
      '/me/reservations/$reservationId/cancel',
    );
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? body) {
    if (body == null) throw StateError('Empty API body');
    final data = body['data'];
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return body;
  }
}
