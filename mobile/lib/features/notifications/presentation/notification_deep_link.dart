import 'package:go_router/go_router.dart';

import '../../../core/navigation/deep_link_listener.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/notification_models.dart';

/// Navigate from an inbox / future OS push payload into the right screen.
void openNotificationDeepLink(
  GoRouter router,
  InboxNotification item, {
  required SessionState session,
}) {
  if (item.isBusinessOps && session.isOwnerMode) {
    router.go('/owner/today');
    return;
  }
  if (item.reservationId != null) {
    // Prefer deep path so OS / future push handlers share routing.
    if (session.isOwnerMode) {
      router.go('/owner/today');
    } else {
      router.go('/bookings');
    }
    return;
  }
  if (item.data['deep_link'] is String) {
    final raw = item.data['deep_link'] as String;
    final uri = Uri.tryParse(raw);
    if (uri != null) {
      openAppDeepLink(router, uri, session: session);
      return;
    }
  }
  if (session.isOwnerMode) {
    router.go('/owner/more');
  } else {
    router.go('/notifications');
  }
}
