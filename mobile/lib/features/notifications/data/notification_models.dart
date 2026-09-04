class InboxNotification {
  const InboxNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    this.data = const {},
    this.readAt,
    this.createdAt,
  });

  final String id;
  final String type;
  final String title;
  final String body;
  final Map<String, dynamic> data;
  final DateTime? readAt;
  final DateTime? createdAt;

  bool get isUnread => readAt == null;

  String? get reservationId {
    final value = data['reservation_id'];
    return value is String && value.isNotEmpty ? value : null;
  }

  bool get isBusinessOps =>
      type.startsWith('business_') ||
      type == 'reservation_checked_in' ||
      type == 'reservation_checked_out';

  factory InboxNotification.fromJson(Map<String, dynamic> json) {
    final rawData = json['data'];
    return InboxNotification(
      id: json['id'] as String,
      type: json['type'] as String? ?? 'system_notification',
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      data: rawData is Map
          ? Map<String, dynamic>.from(rawData)
          : const <String, dynamic>{},
      readAt: _parseTime(json['read_at']),
      createdAt: _parseTime(json['created_at']),
    );
  }

  static DateTime? _parseTime(dynamic value) {
    if (value is! String || value.isEmpty) return null;
    return DateTime.tryParse(value);
  }
}
