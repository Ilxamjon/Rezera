import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/session_controller.dart';
import '../observability/crash_reporting.dart';

/// Handles cold-start and runtime `rezera://` / https deep links.
class DeepLinkListener extends ConsumerStatefulWidget {
  const DeepLinkListener({
    super.key,
    required this.router,
    required this.child,
  });

  final GoRouter router;
  final Widget child;

  @override
  ConsumerState<DeepLinkListener> createState() => _DeepLinkListenerState();
}

class _DeepLinkListenerState extends ConsumerState<DeepLinkListener> {
  final _links = AppLinks();
  StreamSubscription<Uri>? _sub;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    try {
      final initial = await _links.getInitialLink();
      if (initial != null) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          openAppDeepLink(
            widget.router,
            initial,
            session: ref.read(sessionControllerProvider),
          );
        });
      }
      _sub = _links.uriLinkStream.listen((uri) {
        openAppDeepLink(
          widget.router,
          uri,
          session: ref.read(sessionControllerProvider),
        );
      });
    } catch (e, st) {
      await CrashReporting.captureException(e, stackTrace: st);
    }
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

void openAppDeepLink(
  GoRouter router,
  Uri uri, {
  required SessionState session,
}) {
  final host = uri.host;
  final path = uri.path;
  final segments = <String>[
    if (host.isNotEmpty) host,
    ...path.split('/').where((s) => s.isNotEmpty),
  ];

  if (segments.isEmpty) {
    router.go(session.isOwnerMode ? '/owner/dashboard' : '/home');
    return;
  }

  final root = segments.first;

  switch (root) {
    case 'bookings':
      router.go('/bookings');
      return;
    case 'notifications':
      router.go('/notifications');
      return;
    case 'reservation':
      router.go(session.isOwnerMode ? '/owner/today' : '/bookings');
      return;
    case 'owner':
      if (segments.length > 1 && segments[1] == 'today') {
        router.go('/owner/today');
      } else {
        router.go('/owner/dashboard');
      }
      return;
    case 'check-in':
      // QR payload is handled by the scanner screen when in owner mode.
      router.go(session.isOwnerMode ? '/owner/today' : '/bookings');
      return;
    case 'business':
      if (segments.length > 1) {
        router.push('/business/${segments[1]}');
      }
      return;
    case 'book':
      if (segments.length > 1) {
        router.push('/book/${segments[1]}');
      }
      return;
    default:
      router.go(session.isOwnerMode ? '/owner/dashboard' : '/home');
  }
}
