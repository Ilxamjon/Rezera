import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/notification_models.dart';
import '../data/notification_repository.dart';

final notificationRepositoryProvider = Provider<NotificationRepository>((ref) {
  return NotificationRepository(ref.watch(apiClientProvider));
});

final inboxNotificationsProvider =
    FutureProvider.autoDispose<List<InboxNotification>>((ref) async {
  final session = ref.watch(sessionControllerProvider);
  if (!session.isAuthenticated) return const [];
  return ref.watch(notificationRepositoryProvider).list();
});

final unreadCountProvider = StreamProvider.autoDispose<int>((ref) async* {
  final session = ref.watch(sessionControllerProvider);
  if (!session.isAuthenticated) {
    yield 0;
    return;
  }

  final repo = ref.read(notificationRepositoryProvider);
  while (true) {
    try {
      yield await repo.unreadCount();
    } catch (_) {
      yield 0;
    }
    await Future<void>.delayed(const Duration(seconds: 45));
  }
});
