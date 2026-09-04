import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/rezera_theme.dart';
import '../../auth/presentation/session_controller.dart';
import 'notification_providers.dart';

class NotificationBellButton extends ConsumerWidget {
  const NotificationBellButton({super.key, this.color});

  final Color? color;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final count = ref.watch(unreadCountProvider).valueOrNull ?? 0;
    return IconButton(
      tooltip: 'notifications_title'.tr(),
      onPressed: () => context.push('/notifications'),
      icon: Badge(
        isLabelVisible: count > 0,
        backgroundColor: RezeraColors.amber,
        textColor: RezeraColors.ink,
        label: Text(count > 99 ? '99+' : '$count'),
        child: Icon(
          Icons.notifications_outlined,
          color: color ?? RezeraColors.ink,
        ),
      ),
    );
  }
}

class ProfileNavIcon extends ConsumerWidget {
  const ProfileNavIcon({
    super.key,
    required this.outlined,
    this.color,
  });

  final bool outlined;
  final Color? color;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);
    final count = session.isAuthenticated
        ? (ref.watch(unreadCountProvider).valueOrNull ?? 0)
        : 0;
    return Badge(
      isLabelVisible: count > 0,
      backgroundColor: RezeraColors.amber,
      textColor: RezeraColors.ink,
      label: Text(count > 99 ? '99+' : '$count'),
      child: Icon(
        outlined ? Icons.person_outline_rounded : Icons.person_rounded,
        color: color,
      ),
    );
  }
}
