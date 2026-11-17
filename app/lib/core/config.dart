/// تنظیمات اتصال به سایت.
/// برای سایت آزمایشی: flutter build apk --dart-define=HS_SITE=https://staging.example.ir
class AppConfig {
  AppConfig._();

  static const String site = String.fromEnvironment(
    'HS_SITE',
    defaultValue: 'https://hamshagerdionline.ir',
  );

  static const String apiPath = '/wp-json/hamshagerdi/v1';

  static String get apiBase => '$site$apiPath';

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 25);

  /// نام دستگاه که در فهرست نشست‌های کاربر دیده می‌شود
  static const String deviceName = 'اپ اندروید';
}
