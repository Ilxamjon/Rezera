import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_response_cache.dart';
import 'business_models.dart';

final apiResponseCacheProvider = FutureProvider<ApiResponseCache>((ref) async {
  final prefs = await SharedPreferences.getInstance();
  return ApiResponseCache(prefs);
});

class BusinessRepository {
  BusinessRepository(this._api, {ApiResponseCache? cache}) : _cache = cache;

  final ApiClient _api;
  final ApiResponseCache? _cache;

  Future<List<BusinessCard>> discover({
    String? query,
    bool openNow = false,
    double? latitude,
    double? longitude,
    double? radiusKm,
    String? sort,
  }) async {
    final nearby = latitude != null && longitude != null;
    final cacheKey = [
      'discover',
      query ?? '',
      openNow ? '1' : '0',
      nearby ? '${latitude.toStringAsFixed(3)},${longitude.toStringAsFixed(3)}' : '',
      sort ?? '',
    ].join('|');

    try {
      final response = await _api.get<Map<String, dynamic>>(
        '/businesses',
        queryParameters: {
          if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
          if (openNow) 'open_now': true,
          'include_open_now': true,
          'per_page': 20,
          if (nearby) 'latitude': latitude,
          if (nearby) 'longitude': longitude,
          if (nearby) 'radius': radiusKm ?? 15,
          if (nearby) 'sort': sort ?? 'nearest',
        },
      );
      final data = response.data;
      if (data is Map<String, dynamic> && _cache != null) {
        await _cache.write(cacheKey, data);
      }
      return _parseDiscover(data);
    } on NetworkFailure {
      final cached = await _cache?.read(cacheKey);
      if (cached != null) return _parseDiscover(cached);
      rethrow;
    }
  }

  List<BusinessCard> _parseDiscover(Map<String, dynamic>? body) {
    final data = body?['data'];
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
