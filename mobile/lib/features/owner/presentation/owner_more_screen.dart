import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/locale/app_locale.dart';
import '../../../core/theme/rezera_theme.dart';
import '../../auth/presentation/session_controller.dart';

class OwnerMoreScreen extends ConsumerWidget {
  const OwnerMoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);
    final theme = Theme.of(context);
    final membership = session.selectedMembership;

    return Scaffold(
      appBar: AppBar(title: Text('owner_nav_more'.tr())),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: RezeraColors.ink,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  membership?.businessName ?? 'Rezera',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: RezeraColors.onInk,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  membership?.memberRole ?? '',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: RezeraColors.amber,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.language_rounded),
            title: Text('settings_language'.tr()),
            subtitle: Text(AppLocales.labelKey(context.locale).tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => showLanguagePicker(context, ref),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.calendar_month_outlined),
            title: Text('owner_calendar_title'.tr()),
            subtitle: Text('owner_calendar_hint_short'.tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push('/owner/calendar'),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.groups_outlined),
            title: Text('owner_staff_title'.tr()),
            subtitle: Text('owner_staff_hint_short'.tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push('/owner/staff'),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.schedule_rounded),
            title: Text('owner_hours_title'.tr()),
            subtitle: Text('owner_hours_hint_short'.tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push('/owner/working-hours'),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.storefront_outlined),
            title: Text('owner_profile_title'.tr()),
            subtitle: Text('owner_profile_hint_short'.tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push('/owner/business-profile'),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.local_offer_outlined),
            title: Text('owner_promo_title'.tr()),
            subtitle: Text('owner_promo_hint_short'.tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push('/owner/promos'),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.rule_rounded),
            title: Text('owner_rules_title'.tr()),
            subtitle: Text('owner_rules_hint_short'.tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push('/owner/reservation-rules'),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.add_business_outlined),
            title: Text('profile_create_business'.tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push('/create-business'),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.notifications_outlined),
            title: Text('notifications_title'.tr()),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => context.push('/notifications'),
          ),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: () {
              ref.read(sessionControllerProvider.notifier).enterCustomerMode();
              context.go('/home');
            },
            icon: const Icon(Icons.storefront_outlined),
            label: Text('owner_switch_to_customer'.tr()),
          ),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () async {
              await ref.read(sessionControllerProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
            child: Text('profile_logout'.tr()),
          ),
        ],
      ),
    );
  }
}
