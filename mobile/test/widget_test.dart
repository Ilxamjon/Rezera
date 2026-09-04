import 'package:flutter_test/flutter_test.dart';
import 'package:rezera_mobile/features/notifications/data/notification_models.dart';

void main() {
  test('parses inbox notification and reservation deep-link fields', () {
    final item = InboxNotification.fromJson({
      'id': 'n-1',
      'type': 'reservation_confirmed',
      'title': 'Confirmed',
      'body': 'RZ-1',
      'data': {'reservation_id': 'res-9', 'status': 'confirmed'},
      'read_at': null,
      'created_at': '2026-09-03T07:00:00+00:00',
    });

    expect(item.isUnread, isTrue);
    expect(item.reservationId, 'res-9');
    expect(item.isBusinessOps, isFalse);
    expect(item.createdAt, isNotNull);
  });
}
