import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app/app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await EasyLocalization.ensureInitialized();

  runApp(
    EasyLocalization(
      supportedLocales: const [
        Locale('ru'),
        Locale('uz'),
        Locale('en'),
      ],
      path: 'assets/translations',
      fallbackLocale: const Locale('ru'),
      startLocale: const Locale('uz'),
      saveLocale: true,
      child: const ProviderScope(
        child: RezeraApp(),
      ),
    ),
  );
}
