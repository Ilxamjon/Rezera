import '../../../core/network/api_client.dart';
import 'business_models.dart';

class BusinessRepository {
  BusinessRepository(this._api);

  final ApiClient _api;

  Future<List<BusinessCard>> discover({
    String? query,
    bool openNow = false,
  }) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/businesses',
      queryParameters: {
        if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
        if (openNow) 'open_now': true,
        'include_open_now': true,
        'per_page': 20,
      },
    );
    final data = response.data?['data'];
    final items = data is Map ? data['items'] : null;
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map((e) => BusinessCard.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<BusinessDetail> show(String id) async {
    final response = await _api.get<Map<String, dynamic>>('/businesses/$id');
    final data = response.data?['data'];
    if (data is! Map) {
      throw StateError('Missing business payload');
    }
    return BusinessDetail.fromJson(Map<String, dynamic>.from(data));
  }
}
