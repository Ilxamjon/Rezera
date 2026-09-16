import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/locale/app_locale.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../auth/presentation/session_controller.dart';
import '../../notifications/presentation/notification_bell.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);
    final theme = Theme.of(context);
    final user = session.user;

    return Scaffold(
      appBar: AppBar(
        title: Text('profile_title'.tr()),
        actions: const [NotificationBellButton()],
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [RezeraColors.ink, Color(0xFF2A3342)],
              ),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  user?.name ?? 'profile_guest'.tr(),
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: RezeraColors.onInk,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (user != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    user.phone,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      color: RezeraColors.onInk.withValues(alpha: 0.8),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.language_rounded),
            title: Text('settings_language'.tr()),
            subtitle: Text(AppLocales.labelKey(context.locale).tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => showLanguagePicker(context, ref),
          ),
          if (session.isAuthenticated) ...[
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.favorite_outline_rounded),
              title: Text('favorites_title'.tr()),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push('/favorites'),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.notifications_active_outlined),
              title: Text('saved_search_title'.tr()),
              subtitle: Text('saved_search_hint_short'.tr()),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push('/saved-searches'),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.notifications_outlined),
              title: Text('notifications_title'.tr()),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push('/notifications'),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.add_business_outlined),
              title: Text('profile_create_business'.tr()),
              subtitle: Text('profile_create_business_hint'.tr()),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => context.push('/create-business'),
            ),
            if (user?.canUseOwnerMode ?? false) ...[
              const SizedBox(height: 8),
              FilledButton.icon(
                onPressed: () {
                  ref.read(sessionControllerProvider.notifier).enterOwnerMode();
                  context.go('/owner/dashboard');
                },
                icon: const Icon(Icons.dashboard_customize_outlined),
                label: Text('profile_switch_owner'.tr()),
              ),
            ],
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: () async {
                await ref.read(sessionControllerProvider.notifier).logout();
                if (context.mounted) context.go('/login');
              },
              child: Text('profile_logout'.tr()),
            ),
          ] else
            FilledButton(
              onPressed: () => context.go('/login'),
              child: Text('auth_login_action'.tr()),
            ),
        ],
      ),
    );
  }
}
