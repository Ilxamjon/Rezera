import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/navigation/deep_link_listener.dart';
import '../core/theme/rezera_theme.dart';
import '../core/widgets/connectivity_banner.dart';
import '../features/auth/presentation/session_controller.dart';
import 'router.dart';

class RezeraApp extends ConsumerStatefulWidget {
  const RezeraApp({super.key});

  @override
  ConsumerState<RezeraApp> createState() => _RezeraAppState();
}

class _RezeraAppState extends ConsumerState<RezeraApp> {
  @override
  void initState() {
    super.initState();
    // Never block first frame on auth/locale — splash hangs otherwise.
    Future<void>(() async {
      try {
        await ref.read(sessionControllerProvider.notifier).bootstrap();
      } catch (_) {}
      if (!mounted) return;
      try {
        final code = ref.read(sessionControllerProvider).user?.locale;
        if (code == 'uz' || code == 'ru') {
          await context.setLocale(Locale(code!));
        }
      } catch (_) {}
    });
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(goRouterProvider);

    return MaterialApp.router(
      title: 'Rezera',
      debugShowCheckedModeBanner: false,
      theme: buildRezeraTheme(),
      routerConfig: router,
      localizationsDelegates: context.localizationDelegates,
      supportedLocales: context.supportedLocales,
      locale: context.locale,
      builder: (context, child) {
        return DeepLinkListener(
          router: router,
          child: Column(
            children: [
              const ConnectivityBanner(),
              Expanded(child: child ?? const SizedBox.shrink()),
            ],
          ),
        );
      },
    );
  }
}
