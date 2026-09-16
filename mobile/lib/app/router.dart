import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/otp_login_screen.dart';
import '../features/auth/presentation/register_screen.dart';
import '../features/auth/presentation/session_controller.dart';
import '../features/bookings/presentation/book_screen.dart';
import '../features/bookings/presentation/bookings_screen.dart';
import '../features/business/presentation/business_detail_screen.dart';
import '../features/favorites/presentation/favorites_screen.dart';
import '../features/home/presentation/home_screen.dart';
import '../features/notifications/presentation/notifications_screen.dart';
import '../features/owner/presentation/create_business_wizard_screen.dart';
import '../features/owner/presentation/owner_business_profile_screen.dart';
import '../features/owner/presentation/owner_calendar_screen.dart';
import '../features/owner/presentation/owner_dashboard_screen.dart';
import '../features/owner/presentation/owner_more_screen.dart';
import '../features/owner/presentation/owner_promos_screen.dart';
import '../features/owner/presentation/owner_qr_check_in_screen.dart';
import '../features/owner/presentation/owner_reservation_rules_screen.dart';
import '../features/owner/presentation/owner_resource_form_screen.dart';
import '../features/owner/presentation/owner_resource_qr_screen.dart';
import '../features/owner/presentation/owner_resources_screen.dart';
import '../features/owner/presentation/owner_staff_screen.dart';
import '../features/owner/presentation/owner_today_screen.dart';
import '../features/owner/presentation/owner_working_hours_screen.dart';
import '../features/owner/data/owner_models.dart';
import '../features/profile/presentation/profile_screen.dart';
import '../features/saved_searches/presentation/saved_searches_screen.dart';
import 'customer_shell.dart';
import 'owner_shell.dart';

final _rootKey = GlobalKey<NavigatorState>();

final goRouterProvider = Provider<GoRouter>((ref) {
  // Do NOT watch session here — recreating GoRouter on every auth change
  // blanks the app / blocks login navigation.
  final refresh = _SessionListenable(ref);

  return GoRouter(
    navigatorKey: _rootKey,
    initialLocation: '/home',
    refreshListenable: refresh,
    redirect: (context, state) {
      final session = ref.read(sessionControllerProvider);
      final status = session.status;
      final loc = state.matchedLocation;
      final loggingIn =
          loc == '/login' || loc == '/register' || loc == '/login/otp';
      final inOwner = loc.startsWith('/owner');
      final creatingBusiness = loc == '/create-business';

      if (status == AuthStatus.unknown) {
        return null;
      }

      if ((creatingBusiness || loc == '/saved-searches') &&
          status != AuthStatus.authenticated) {
        return '/login';
      }

      if (status == AuthStatus.authenticated && loggingIn) {
        return session.isOwnerMode ? '/owner/dashboard' : '/home';
      }

      if (session.isOwnerMode && !inOwner && !loggingIn && !creatingBusiness) {
        // Keep overlay / shared routes reachable from owner mode.
        if (loc.startsWith('/book') ||
            loc.startsWith('/business') ||
            loc == '/notifications' ||
            loc == '/favorites' ||
            loc == '/saved-searches') {
          return null;
        }
        if (loc == '/home' || loc == '/bookings' || loc == '/profile') {
          return '/owner/dashboard';
        }
      }

      if (!session.isOwnerMode && inOwner) {
        return '/home';
      }

      if (inOwner &&
          (status != AuthStatus.authenticated ||
              !(session.user?.canUseOwnerMode ?? false))) {
        return '/home';
      }

      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/login/otp',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const OtpLoginScreen(),
      ),
      GoRoute(
        path: '/register',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const RegisterScreen(),
      ),
      // Overlay routes must use the root navigator so push/pop + system back work
      // above StatefulShellRoute (otherwise canPop stays false / back exits app).
      GoRoute(
        path: '/business/:id',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => BusinessDetailScreen(
          businessId: state.pathParameters['id']!,
        ),
      ),
      GoRoute(
        path: '/book/:businessId',
        parentNavigatorKey: _rootKey,
        builder: (context, state) {
          final extra = state.extra;
          final name = extra is Map && extra['name'] is String
              ? extra['name'] as String
              : 'Rezera';
          return BookScreen(
            businessId: state.pathParameters['businessId']!,
            businessName: name,
            preselectedResourceId:
                extra is Map ? extra['resourceId'] as String? : null,
          );
        },
      ),
      GoRoute(
        path: '/notifications',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const NotificationsScreen(),
      ),
      GoRoute(
        path: '/favorites',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const FavoritesScreen(),
      ),
      GoRoute(
        path: '/saved-searches',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const SavedSearchesScreen(),
      ),
      GoRoute(
        path: '/create-business',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const CreateBusinessWizardScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return CustomerShell(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/home',
                builder: (context, state) => const HomeScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/bookings',
                builder: (context, state) => const BookingsScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/profile',
                builder: (context, state) => const ProfileScreen(),
              ),
            ],
          ),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return OwnerShell(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/owner/dashboard',
                builder: (context, state) => const OwnerDashboardScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/owner/today',
                builder: (context, state) => const OwnerTodayScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/owner/resources',
                builder: (context, state) => const OwnerResourcesScreen(),
                routes: [
                  GoRoute(
                    path: 'new',
                    parentNavigatorKey: _rootKey,
                    builder: (context, state) =>
                        const OwnerResourceFormScreen(),
                  ),
                  GoRoute(
                    path: ':resourceId/edit',
                    parentNavigatorKey: _rootKey,
                    builder: (context, state) {
                      final extra = state.extra;
                      return OwnerResourceFormScreen(
                        existing: extra is OwnerResource ? extra : null,
                      );
                    },
                  ),
                  GoRoute(
                    path: ':resourceId/qr',
                    parentNavigatorKey: _rootKey,
                    builder: (context, state) {
                      final extra = state.extra;
                      if (extra is! OwnerResource) {
                        return const Scaffold(
                          body: Center(child: Text('…')),
                        );
                      }
                      return OwnerResourceQrScreen(resource: extra);
                    },
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/owner/more',
                builder: (context, state) => const OwnerMoreScreen(),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: '/owner/check-in/qr',
        parentNavigatorKey: _rootKey,
        builder: (context, state) {
          final extra = state.extra;
          final map = extra is Map ? extra : const {};
          return OwnerQrCheckInScreen(
            reservationId: map['reservationId'] as String? ?? '',
            customerName: map['customerName'] as String? ?? 'Guest',
          );
        },
      ),
      GoRoute(
        path: '/owner/reservation-rules',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const OwnerReservationRulesScreen(),
      ),
      GoRoute(
        path: '/owner/working-hours',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const OwnerWorkingHoursScreen(),
      ),
      GoRoute(
        path: '/owner/business-profile',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const OwnerBusinessProfileScreen(),
      ),
      GoRoute(
        path: '/owner/calendar',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const OwnerCalendarScreen(),
      ),
      GoRoute(
        path: '/owner/staff',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const OwnerStaffScreen(),
      ),
      GoRoute(
        path: '/owner/promos',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const OwnerPromosScreen(),
      ),
    ],
  );
});

class _SessionListenable extends ChangeNotifier {
  _SessionListenable(this._ref) {
    _ref.listen<SessionState>(sessionControllerProvider, (previous, next) {
      notifyListeners();
    });
  }

  final Ref _ref;
}
