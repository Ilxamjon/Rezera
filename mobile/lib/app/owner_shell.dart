import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme/rezera_theme.dart';
import '../../features/notifications/presentation/notification_bell.dart';

class OwnerShell extends StatelessWidget {
  const OwnerShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  void _onTap(int index) {
    navigationShell.goBranch(
      index,
      initialLocation: index == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
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
          onDestinationSelected: _onTap,
          backgroundColor: RezeraColors.ink,
          indicatorColor: RezeraColors.amber.withValues(alpha: 0.35),
          destinations: [
            NavigationDestination(
              icon: Icon(
                Icons.dashboard_outlined,
                color: RezeraColors.onInk.withValues(alpha: 0.7),
              ),
              selectedIcon: const Icon(
                Icons.dashboard_rounded,
                color: RezeraColors.amber,
              ),
              label: 'owner_nav_dashboard'.tr(),
            ),
            NavigationDestination(
              icon: Icon(
                Icons.today_outlined,
                color: RezeraColors.onInk.withValues(alpha: 0.7),
              ),
              selectedIcon: const Icon(
                Icons.today_rounded,
                color: RezeraColors.amber,
              ),
              label: 'owner_nav_today'.tr(),
            ),
            NavigationDestination(
              icon: Icon(
                Icons.desktop_windows_outlined,
                color: RezeraColors.onInk.withValues(alpha: 0.7),
              ),
              selectedIcon: const Icon(
                Icons.desktop_windows_rounded,
                color: RezeraColors.amber,
              ),
              label: 'owner_nav_resources'.tr(),
            ),
            NavigationDestination(
              icon: ProfileNavIcon(
                outlined: true,
                color: RezeraColors.onInk.withValues(alpha: 0.7),
              ),
              selectedIcon: const ProfileNavIcon(
                outlined: false,
                color: RezeraColors.amber,
              ),
              label: 'owner_nav_more'.tr(),
            ),
          ],
        ),
      ),
    );
  }
}
