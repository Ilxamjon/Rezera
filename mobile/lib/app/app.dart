import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/theme/rezera_theme.dart';
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
    Future.microtask(() async {
      await ref.read(sessionControllerProvider.notifier).bootstrap();
      if (!mounted) return;
      final code = ref.read(sessionControllerProvider).user?.locale;
      if (code == 'uz' || code == 'ru') {
        await context.setLocale(Locale(code!));
      }
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
    );
  }
}
