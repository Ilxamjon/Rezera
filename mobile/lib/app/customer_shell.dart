import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme/rezera_theme.dart';
import '../../features/bookings/presentation/reservation_providers.dart';
import '../../features/notifications/presentation/notification_bell.dart';

class CustomerShell extends ConsumerWidget {
  const CustomerShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  void _onTap(WidgetRef ref, int index) {
    navigationShell.goBranch(
      index,
      initialLocation: index == navigationShell.currentIndex,
    );
    // IndexedStack keeps tab state; refresh bookings when opening the tab.
    if (index == 1) {
      ref.invalidate(myReservationsProvider);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final atRootTab = navigationShell.currentIndex == 0;

    return PopScope(
      canPop: atRootTab,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) return;
        if (!atRootTab) {
          navigationShell.goBranch(0);
        }
      },
      child: Scaffold(
        body: navigationShell,
        bottomNavigationBar: NavigationBar(
          selectedIndex: navigationShell.currentIndex,
          onDestinationSelected: (index) => _onTap(ref, index),
          backgroundColor: Colors.white.withValues(alpha: 0.92),
          indicatorColor: RezeraColors.amber.withValues(alpha: 0.35),
          destinations: [
            NavigationDestination(
              icon: const Icon(Icons.explore_outlined),
              selectedIcon: const Icon(Icons.explore_rounded),
              label: 'nav_home'.tr(),
            ),
            NavigationDestination(
              icon: const Icon(Icons.event_note_outlined),
              selectedIcon: const Icon(Icons.event_note_rounded),
              label: 'nav_bookings'.tr(),
            ),
            NavigationDestination(
              icon: const ProfileNavIcon(outlined: true),
              selectedIcon: const ProfileNavIcon(outlined: false),
              label: 'nav_profile'.tr(),
            ),
          ],
        ),
      ),
    );
  }
}
