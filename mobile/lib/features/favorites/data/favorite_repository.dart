import '../../../core/network/api_client.dart';
import '../../search/data/business_models.dart';

class FavoriteRepository {
  FavoriteRepository(this._api);

  final ApiClient _api;

  Future<List<BusinessCard>> list() async {
    final response = await _api.get<Map<String, dynamic>>(
      '/me/favorites/businesses',
      queryParameters: {'per_page': 50},
    );
    final data = response.data?['data'];
    final items = data is Map ? data['items'] : null;
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map((e) => BusinessCard.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<bool> add(String businessId) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/me/favorites/businesses/$businessId',
    );
    return _isFavorite(response.data?['data']);
  }

  Future<bool> remove(String businessId) async {
    final response = await _api.delete<Map<String, dynamic>>(
      '/me/favorites/businesses/$businessId',
    );
    return _isFavorite(response.data?['data']);
  }

  Future<bool> toggle(String businessId, {required bool currentlyFavorite}) {
    return currentlyFavorite ? remove(businessId) : add(businessId);
  }

  bool _isFavorite(dynamic data) {
    if (data is Map && data['is_favorite'] is bool) {
      return data['is_favorite'] as bool;
    }
    return false;
  }
}
