import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_failure.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../auth/presentation/session_controller.dart';
import '../data/notification_models.dart';
import 'notification_providers.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);
    final async = ref.watch(inboxNotificationsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text('notifications_title'.tr()),
        actions: [
          if (session.isAuthenticated)
            TextButton(
              onPressed: () => _markAll(context, ref),
              child: Text('notifications_mark_all'.tr()),
            ),
        ],
      ),
      body: !session.isAuthenticated
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'notifications_login_required'.tr(),
                      textAlign: TextAlign.center,
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: () => context.push('/login'),
                      child: Text('auth_login_action'.tr()),
                    ),
                  ],
                ),
              ),
            )
          : async.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(
                child: Text(
                  e is AppFailure ? e.message.tr() : 'error_unknown'.tr(),
                ),
              ),
              data: (items) {
                if (items.isEmpty) {
                  return Center(child: Text('notifications_empty'.tr()));
                }
                return RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(inboxNotificationsProvider);
                    ref.invalidate(unreadCountProvider);
                  },
                  child: ListView.separated(
                    padding: const EdgeInsets.all(20),
                    itemCount: items.length,
                    separatorBuilder: (context, index) =>
                        const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final item = items[index];
                      return _NotificationTile(
                        notification: item,
                        onTap: () => _open(context, ref, item),
                      );
                    },
                  ),
                );
              },
            ),
    );
  }

  Future<void> _markAll(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(notificationRepositoryProvider).markAllRead();
      ref.invalidate(inboxNotificationsProvider);
      ref.invalidate(unreadCountProvider);
    } on AppFailure catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message.tr())),
        );
      }
    }
  }

  Future<void> _open(
    BuildContext context,
    WidgetRef ref,
    InboxNotification item,
  ) async {
    if (item.isUnread) {
      try {
        await ref.read(notificationRepositoryProvider).markRead(item.id);
        ref.invalidate(inboxNotificationsProvider);
        ref.invalidate(unreadCountProvider);
      } catch (_) {}
    }

    if (!context.mounted) return;
    final session = ref.read(sessionControllerProvider);
    if (item.isBusinessOps && session.isOwnerMode) {
      context.go('/owner/today');
      return;
    }
    if (item.reservationId != null) {
      if (session.isOwnerMode) {
        context.go('/owner/today');
      } else {
        context.go('/bookings');
      }
    }
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({
    required this.notification,
    required this.onTap,
  });

  final InboxNotification notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final created = notification.createdAt;
    final when = created == null
        ? ''
        : DateFormat.MMMd(Intl.getCurrentLocale())
            .add_Hm()
            .format(created.toLocal());

    return Material(
      color: notification.isUnread
          ? RezeraColors.amber.withValues(alpha: 0.12)
          : Colors.white.withValues(alpha: 0.8),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: notification.isUnread
                  ? RezeraColors.amber.withValues(alpha: 0.5)
                  : RezeraColors.sand,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      notification.title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  if (notification.isUnread)
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: RezeraColors.amberDeep,
                        shape: BoxShape.circle,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 6),
              Text(notification.body, style: theme.textTheme.bodyLarge),
              if (when.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(when, style: theme.textTheme.labelMedium),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
