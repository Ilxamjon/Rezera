import 'package:flutter/material.dart';

/// Rezera visual system — slate + amber, not purple-default AI chrome.
///
/// Uses platform fonts (no Google Fonts runtime fetch). Fetching Sora/Manrope
/// on first frame hung the Android splash when fonts.gstatic.com was slow/blocked.
abstract final class RezeraColors {
  static const ink = Color(0xFF14181F);
  static const slate = Color(0xFF1E2530);
  static const mist = Color(0xFFF3F0EA);
  static const sand = Color(0xFFE8E2D6);
  static const amber = Color(0xFFE8A317);
  static const amberDeep = Color(0xFFC4840A);
  static const seafoam = Color(0xFF2F6F6A);
  static const danger = Color(0xFFB42318);
  static const onInk = Color(0xFFF7F4EE);
}

ThemeData buildRezeraTheme() {
  final base = ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: ColorScheme.light(
      primary: RezeraColors.ink,
      onPrimary: RezeraColors.onInk,
      secondary: RezeraColors.amber,
      onSecondary: RezeraColors.ink,
      surface: RezeraColors.mist,
      onSurface: RezeraColors.ink,
      error: RezeraColors.danger,
      outline: RezeraColors.sand,
    ),
    scaffoldBackgroundColor: RezeraColors.mist,
  );

  final text = base.textTheme;

  return base.copyWith(
    textTheme: text.copyWith(
      displayLarge: text.displayLarge?.copyWith(
        fontWeight: FontWeight.w700,
        color: RezeraColors.ink,
        letterSpacing: -1.2,
      ),
      displayMedium: text.displayMedium?.copyWith(
        fontWeight: FontWeight.w700,
        color: RezeraColors.ink,
      ),
      headlineLarge: text.headlineLarge?.copyWith(
        fontWeight: FontWeight.w700,
        color: RezeraColors.ink,
        letterSpacing: -0.8,
      ),
      headlineMedium: text.headlineMedium?.copyWith(
        fontWeight: FontWeight.w600,
        color: RezeraColors.ink,
      ),
      titleLarge: text.titleLarge?.copyWith(
        fontWeight: FontWeight.w600,
        color: RezeraColors.ink,
      ),
      bodyLarge: text.bodyLarge?.copyWith(height: 1.45),
      bodyMedium: text.bodyMedium?.copyWith(height: 1.4),
      labelLarge: text.labelLarge?.copyWith(fontWeight: FontWeight.w600),
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: RezeraColors.mist,
      foregroundColor: RezeraColors.ink,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: text.titleLarge?.copyWith(
        fontWeight: FontWeight.w700,
        color: RezeraColors.ink,
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white.withValues(alpha: 0.72),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: RezeraColors.sand),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: RezeraColors.sand),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: RezeraColors.ink, width: 1.4),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: RezeraColors.ink,
        foregroundColor: RezeraColors.onInk,
        minimumSize: const Size.fromHeight(52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: text.labelLarge?.copyWith(fontWeight: FontWeight.w700),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: RezeraColors.seafoam,
        textStyle: text.labelLarge?.copyWith(fontWeight: FontWeight.w600),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: RezeraColors.sand,
      labelStyle: text.labelMedium?.copyWith(fontWeight: FontWeight.w600),
      side: BorderSide.none,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      padding: const EdgeInsets.symmetric(horizontal: 8),
    ),
    dividerTheme: const DividerThemeData(color: RezeraColors.sand),
  );
}
