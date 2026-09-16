import '../../../core/network/api_client.dart';

class SavedSearchItem {
  const SavedSearchItem({
    required this.id,
    required this.name,
    required this.alertEnabled,
    this.city,
    this.searchQuery,
    this.alertChannel,
    this.alertFrequency,
  });

  final String id;
  final String name;
  final bool alertEnabled;
  final String? city;
  final String? searchQuery;
  final String? alertChannel;
  final String? alertFrequency;

  factory SavedSearchItem.fromJson(Map<String, dynamic> json) {
    final filters = json['filters'] is Map
        ? Map<String, dynamic>.from(json['filters'] as Map)
        : <String, dynamic>{};
    final alert = json['alert'] is Map
        ? Map<String, dynamic>.from(json['alert'] as Map)
        : <String, dynamic>{};
    return SavedSearchItem(
      id: json['id'] as String,
      name: json['name'] as String? ?? '',
      alertEnabled: alert['enabled'] as bool? ?? false,
      city: filters['city'] as String?,
      searchQuery: filters['search_query'] as String?,
      alertChannel: alert['channel'] as String?,
      alertFrequency: alert['frequency'] as String?,
    );
  }
}

class SavedSearchRepository {
  SavedSearchRepository(this._api);

  final ApiClient _api;

  Future<List<SavedSearchItem>> list() async {
    final response = await _api.get<Map<String, dynamic>>(
      '/me/saved-searches',
      queryParameters: {'per_page': 50},
    );
    return _items(response.data).map(SavedSearchItem.fromJson).toList();
  }

  Future<SavedSearchItem> create({
    required String name,
    String? city,
    String? searchQuery,
    bool alertEnabled = true,
    String dateMode = 'next_available',
  }) async {
    final response = await _api.post<Map<String, dynamic>>(
      '/me/saved-searches',
      data: {
        'name': name,
        'date_mode': dateMode,
        'availability_required': true,
        'alert_enabled': alertEnabled,
        'alert_channel': 'in_app',
        'alert_frequency': 'instant',
        if (city != null && city.isNotEmpty) 'city': city,
        if (searchQuery != null && searchQuery.isNotEmpty)
          'search_query': searchQuery,
      },
    );
    return SavedSearchItem.fromJson(_unwrap(response.data));
  }

  Future<void> delete(String id) async {
    await _api.delete<Map<String, dynamic>>('/me/saved-searches/$id');
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
