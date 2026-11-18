import 'package:flutter/material.dart';

/// رنگ‌های برند (از لوگو و قالب سایت)
class HsColors {
  HsColors._();
  static const orange = Color(0xFFF57C00);
  static const orangeDark = Color(0xFFC24E00);
  static const navy = Color(0xFF16213E);
  static const blue = Color(0xFF2C5DA8);
  static const cream = Color(0xFFFFF8F2);
  static const green = Color(0xFF2EA043);
  static const red = Color(0xFFD64545);
  static const amber = Color(0xFFF2A900);

  /// رنگ دایره‌ی آواتار (حرف اول نام)
  static const avatarPalette = <Color>[
    Color(0xFFF57C00),
    Color(0xFF2C5DA8),
    Color(0xFF2EA043),
    Color(0xFF8E44AD),
    Color(0xFFD64545),
    Color(0xFF00897B),
    Color(0xFFC2185B),
    Color(0xFF5D4037),
  ];
}

class HsTheme {
  HsTheme._();

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: HsColors.orange,
      primary: HsColors.orangeDark,
      secondary: HsColors.blue,
      surface: Colors.white,
      brightness: Brightness.light,
    );
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      fontFamily: 'Vazirmatn',
      scaffoldBackgroundColor: HsColors.cream,
    );
    const title = TextStyle(fontFamily: 'Lalezar', fontFamilyFallback: ['Vazirmatn'], color: HsColors.navy, height: 1.4);
    return base.copyWith(
      textTheme: base.textTheme.copyWith(
        displaySmall: base.textTheme.displaySmall?.merge(title),
        headlineMedium: base.textTheme.headlineMedium?.merge(title),
        headlineSmall: base.textTheme.headlineSmall?.merge(title),
        titleLarge: base.textTheme.titleLarge?.merge(title),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: HsColors.cream,
        foregroundColor: HsColors.navy,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(fontFamily: 'Lalezar', fontFamilyFallback: ['Vazirmatn'], fontSize: 22, color: HsColors.navy),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: const BorderSide(color: Color(0x14000000)),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: HsColors.orange,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(54),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          textStyle: const TextStyle(fontFamily: 'Vazirmatn', fontSize: 17, fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(50),
          foregroundColor: HsColors.navy,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          side: const BorderSide(color: Color(0x33000000)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        // متن راهنمای داخل کادر خیلی کم‌رنگ باشد تا با متن واقعی اشتباه نشود
        hintStyle: const TextStyle(color: Color(0x33000000), fontWeight: FontWeight.w400),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0x22000000))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0x22000000))),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: HsColors.orange, width: 2)),
      ),
      snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
    );
  }
}
