import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme/rezera_theme.dart';
import '../../features/notifications/presentation/notification_bell.dart';

class CustomerShell extends StatelessWidget {
  const CustomerShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  void _onTap(int index) {
    navigationShell.goBranch(
      index,
      initialLocation: index == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: _onTap,
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
    );
  }
}
