import '../../../core/network/api_client.dart';
import 'owner_models.dart';

class OwnerRepository {
  OwnerRepository(this._api);

  final ApiClient _api;

  Future<List<ManagedBusiness>> businesses() async {
    final response = await _api.get<Map<String, dynamic>>(
      '/manage/businesses',
      queryParameters: {'per_page': 50},
    );
    return _items(response.data)
        .map((e) => ManagedBusiness.fromJson(e))
        .toList();
  }

  Future<OwnerDashboard> dashboard(String businessId) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/manage/businesses/$businessId/dashboard',
      queryParameters: {'upcoming_limit': 8},
    );
    return OwnerDashboard.fromJson(_unwrap(response.data));
  }

  Future<List<OwnerReservation>> todayReservations(String businessId) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/manage/businesses/$businessId/reservations/today',
      queryParameters: {'per_page': 50},
    );
    return _items(response.data)
        .map((e) => OwnerReservation.fromJson(e))
        .toList();
  }

  Future<List<OwnerResource>> resources(String businessId) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/manage/businesses/$businessId/resources',
      queryParameters: {'per_page': 50},
    );
    return _items(response.data).map((e) => OwnerResource.fromJson(e)).toList();
  }

  Future<List<ResourceCategoryOption>> resourceCategories(
    String businessId,
  ) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/manage/businesses/$businessId/resource-categories',
    );
    final data = response.data?['data'];
    if (data is List) {
      return data
          .whereType<Map>()
          .map(
            (e) => ResourceCategoryOption.fromJson(Map<String, dynamic>.from(e)),
          )
          .toList();
    }
    return _items(response.data)
        .map((e) => ResourceCategoryOption.fromJson(e))
        .toList();
  }

  Future<OwnerResource> createResource({
    required String businessId,
    required Map<String, dynamic> payload,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/manage/businesses/$businessId/resources',
      data: payload,
    );
    return OwnerResource.fromJson(_unwrap(response.data));
  }

  Future<OwnerResource> updateResource({
    required String businessId,
    required String resourceId,
    required Map<String, dynamic> payload,
  }) async {
    final response = await _api.patch<Map<String, dynamic>>(
      '/manage/businesses/$businessId/resources/$resourceId',
      data: payload,
    );
    return OwnerResource.fromJson(_unwrap(response.data));
  }

  Future<void> deleteResource({
    required String businessId,
    required String resourceId,
  }) async {
    await _api.delete<Map<String, dynamic>>(
      '/manage/businesses/$businessId/resources/$resourceId',
    );
  }

  Future<ResourceQrPayload> generateResourceQr({
    required String businessId,
    required String resourceId,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/manage/businesses/$businessId/resources/$resourceId/qr',
    );
    return ResourceQrPayload.fromJson(_unwrap(response.data));
  }

  Future<OwnerReservation> changeStatus({
    required String businessId,
    required String reservationId,
    required String status,
    String? reason,
  }) async {
    final response = await _api.patch<Map<String, dynamic>>(
      '/manage/businesses/$businessId/reservations/$reservationId',
      data: {
        'status': status,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      },
    );
    return OwnerReservation.fromJson(_unwrap(response.data));
  }

  Future<OwnerReservation> checkIn({
    required String businessId,
    required String reservationId,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/manage/businesses/$businessId/reservations/$reservationId/check-in',
    );
    return OwnerReservation.fromJson(_unwrap(response.data));
  }

  Future<OwnerReservation> checkInQr({
    required String businessId,
    required String reservationId,
    required String qrToken,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/manage/businesses/$businessId/reservations/$reservationId/check-in/qr',
      data: {'qr_token': qrToken},
    );
    return OwnerReservation.fromJson(_unwrap(response.data));
  }

  Future<OwnerReservation> checkOut({
    required String businessId,
    required String reservationId,
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/manage/businesses/$businessId/reservations/$reservationId/check-out',
    );
    return OwnerReservation.fromJson(_unwrap(response.data));
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? body) {
    if (body == null) throw StateError('Empty API body');
    final data = body['data'];
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return body;
  }

  List<Map<String, dynamic>> _items(Map<String, dynamic>? body) {
    final data = body?['data'];
    final items = data is Map ? data['items'] : (data is List ? data : null);
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }
}
