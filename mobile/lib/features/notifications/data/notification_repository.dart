import '../../../core/network/api_client.dart';
import 'notification_models.dart';

class NotificationRepository {
  NotificationRepository(this._api);

  final ApiClient _api;

  Future<List<InboxNotification>> list({bool unreadOnly = false}) async {
    final response = await _api.get<Map<String, dynamic>>(
      '/me/notifications',
      queryParameters: {
        'per_page': 50,
        if (unreadOnly) 'unread': 1,
      },
    );
    final data = response.data?['data'];
    final items = data is Map ? data['items'] : (data is List ? data : null);
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map(
          (e) => InboxNotification.fromJson(Map<String, dynamic>.from(e)),
        )
        .toList();
  }

  Future<int> unreadCount() async {
    final response = await _api.get<Map<String, dynamic>>(
      '/me/notifications/unread-count',
    );
    final data = response.data?['data'];
    if (data is Map && data['count'] is int) {
      return data['count'] as int;
    }
    if (data is Map && data['count'] is num) {
      return (data['count'] as num).toInt();
    }
    return 0;
  }

  Future<InboxNotification> markRead(String id) async {
    final response = await _api.patch<Map<String, dynamic>>(
      '/me/notifications/$id/read',
    );
    return InboxNotification.fromJson(_unwrap(response.data));
  }

  Future<void> markAllRead() async {
    await _api.post<Map<String, dynamic>>('/me/notifications/read-all');
  }

  Map<String, dynamic> _unwrap(Map<String, dynamic>? body) {
    if (body == null) throw StateError('Empty API body');
    final data = body['data'];
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return body;
  }
}
