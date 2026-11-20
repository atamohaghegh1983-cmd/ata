import 'package:flutter/material.dart';

/// رنگ‌های برند (از لوگو و قالب سایت)
class HsColors {
  HsColors._();

  /// حالت تاریک فعال است؟ (در app.dart پیش از ساختن صفحه‌ها تنظیم می‌شود)
  static bool dark = false;

  static const orange = Color(0xFFF57C00);
  static const orangeDark = Color(0xFFC24E00);

  /// رنگ اصلی متن تیره (در حالت تاریک روشن می‌شود)
  static Color get navy => dark ? const Color(0xFFE4E9F5) : const Color(0xFF16213E);

  /// سرمه‌ای ثابت (QR و تصاویری که روی زمینه‌ی سفید چاپ می‌شوند)
  static const ink = Color(0xFF16213E);
  static const blue = Color(0xFF2C5DA8);
  static Color get cream => dark ? const Color(0xFF12161F) : const Color(0xFFFFF8F2);

  /// زمینه‌ی کارت‌ها و کادرها
  static Color get card => dark ? const Color(0xFF1C2230) : Colors.white;

  /// متن‌های کم‌رنگ (به جای black54 / black45 / black38 / black26 / black87)
  static Color get muted => dark ? Colors.white70 : Colors.black54;
  static Color get faint => dark ? Colors.white60 : Colors.black45;
  static Color get faint2 => dark ? Colors.white38 : Colors.black38;
  static Color get line => dark ? Colors.white24 : Colors.black26;
  static Color get text87 => dark ? Colors.white : Colors.black87;

  /// حباب پیام خودم
  static Color get bubbleMine => dark ? const Color(0xFF5A3A1E) : const Color(0xFFFFE3CC);
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

  /// تم بر اساس HsColors.dark (روشن یا تاریک)
  static ThemeData light() {
    final dark = HsColors.dark;
    final scheme = ColorScheme.fromSeed(
      seedColor: HsColors.orange,
      primary: dark ? HsColors.orange : HsColors.orangeDark,
      secondary: HsColors.blue,
      surface: HsColors.card,
      brightness: dark ? Brightness.dark : Brightness.light,
    );
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      fontFamily: 'Vazirmatn',
      scaffoldBackgroundColor: HsColors.cream,
    );
    final title = TextStyle(fontFamily: 'Lalezar', fontFamilyFallback: ['Vazirmatn'], color: HsColors.navy, height: 1.4);
    return base.copyWith(
      textTheme: base.textTheme.copyWith(
        displaySmall: base.textTheme.displaySmall?.merge(title),
        headlineMedium: base.textTheme.headlineMedium?.merge(title),
        headlineSmall: base.textTheme.headlineSmall?.merge(title),
        titleLarge: base.textTheme.titleLarge?.merge(title),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: HsColors.cream,
        foregroundColor: HsColors.navy,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(fontFamily: 'Lalezar', fontFamilyFallback: ['Vazirmatn'], fontSize: 22, color: HsColors.navy),
      ),
      cardTheme: CardThemeData(
        color: HsColors.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: dark ? const Color(0x22FFFFFF) : const Color(0x14000000)),
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
          side: BorderSide(color: dark ? const Color(0x44FFFFFF) : const Color(0x33000000)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: HsColors.card,
        // متن راهنمای داخل کادر خیلی کم‌رنگ باشد تا با متن واقعی اشتباه نشود
        hintStyle: TextStyle(color: dark ? const Color(0x44FFFFFF) : const Color(0x33000000), fontWeight: FontWeight.w400),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: dark ? const Color(0x33FFFFFF) : const Color(0x22000000))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: dark ? const Color(0x33FFFFFF) : const Color(0x22000000))),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: HsColors.orange, width: 2)),
      ),
      snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
    );
  }
}
