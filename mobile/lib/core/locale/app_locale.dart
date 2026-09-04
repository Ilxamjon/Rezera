import 'package:easy_localization/easy_localization.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';
import '../storage/token_storage.dart';

/// App UI locales. Backend profile accepts uz/kaa/ru — English stays client-only.
class AppLocales {
  AppLocales._();

  static const supported = <Locale>[
    Locale('uz'),
    Locale('ru'),
    Locale('en'),
  ];

  static String labelKey(Locale locale) => switch (locale.languageCode) {
        'uz' => 'lang_uz',
        'en' => 'lang_en',
        _ => 'lang_ru',
      };

  /// Value safe to PATCH to `/me/profile`.
  static String? apiLocale(Locale locale) => switch (locale.languageCode) {
        'uz' => 'uz',
        'kaa' => 'kaa',
        'ru' => 'ru',
        _ => null,
      };
}

Future<void> applyAppLocale(BuildContext context, Locale locale) async {
  await context.setLocale(locale);
}

Future<void> syncLocaleToProfile(WidgetRef ref, Locale locale) async {
  final code = AppLocales.apiLocale(locale);
  if (code == null) return;
  final token = await ref.read(tokenStorageProvider).readToken();
  if (token == null || token.isEmpty) return;
  try {
    await ref.read(apiClientProvider).patch<Map<String, dynamic>>(
      '/me/profile',
      data: {'locale': code},
    );
  } catch (_) {
    // Locale still applied locally.
  }
}

Future<void> showLanguagePicker(BuildContext context, WidgetRef ref) async {
  final current = context.locale;
  await showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (sheetContext) {
      return SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text('settings_language'.tr()),
            ),
            for (final locale in AppLocales.supported)
              ListTile(
                leading: Icon(
                  locale.languageCode == current.languageCode
                      ? Icons.radio_button_checked
                      : Icons.radio_button_off,
                ),
                title: Text(AppLocales.labelKey(locale).tr()),
                onTap: () async {
                  Navigator.of(sheetContext).pop();
                  await applyAppLocale(context, locale);
                  await syncLocaleToProfile(ref, locale);
                },
              ),
            const SizedBox(height: 8),
          ],
        ),
      );
    },
  );
}
