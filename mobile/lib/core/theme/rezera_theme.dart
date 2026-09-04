import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Rezera visual system — slate + amber, not purple-default AI chrome.
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
  final display = GoogleFonts.soraTextTheme();
  final body = GoogleFonts.manropeTextTheme();

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

  return base.copyWith(
    textTheme: body.copyWith(
      displayLarge: display.displayLarge?.copyWith(
        fontWeight: FontWeight.w700,
        color: RezeraColors.ink,
        letterSpacing: -1.2,
      ),
      displayMedium: display.displayMedium?.copyWith(
        fontWeight: FontWeight.w700,
        color: RezeraColors.ink,
      ),
      headlineLarge: display.headlineLarge?.copyWith(
        fontWeight: FontWeight.w700,
        color: RezeraColors.ink,
        letterSpacing: -0.8,
      ),
      headlineMedium: display.headlineMedium?.copyWith(
        fontWeight: FontWeight.w600,
        color: RezeraColors.ink,
      ),
      titleLarge: display.titleLarge?.copyWith(
        fontWeight: FontWeight.w600,
        color: RezeraColors.ink,
      ),
      bodyLarge: body.bodyLarge?.copyWith(height: 1.45),
      bodyMedium: body.bodyMedium?.copyWith(height: 1.4),
      labelLarge: body.labelLarge?.copyWith(fontWeight: FontWeight.w600),
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: RezeraColors.mist,
      foregroundColor: RezeraColors.ink,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: display.titleLarge?.copyWith(
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
        textStyle: body.labelLarge?.copyWith(fontWeight: FontWeight.w700),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: RezeraColors.seafoam,
        textStyle: body.labelLarge?.copyWith(fontWeight: FontWeight.w600),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: RezeraColors.sand,
      labelStyle: body.labelMedium?.copyWith(fontWeight: FontWeight.w600),
      side: BorderSide.none,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      padding: const EdgeInsets.symmetric(horizontal: 8),
    ),
    dividerTheme: const DividerThemeData(color: RezeraColors.sand),
  );
}
