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
  static String get deviceName => isTeacherApp ? 'اپ همشاگردی معلم' : 'اپ همشاگردی';

  /// کدام اپ: family (والدین و دانش‌آموزان) یا teacher (کادر مدرسه). در ساخت با --dart-define=HS_APP=teacher
  static String app = const String.fromEnvironment('HS_APP', defaultValue: 'family');
  static bool get isTeacherApp => app == 'teacher';
  static String get appName => isTeacherApp ? 'همشاگردی معلم' : 'همشاگردی';
  static String get otherApp => isTeacherApp ? 'family' : 'teacher';
  static String get otherAppName => isTeacherApp ? 'همشاگردی (والدین و دانش‌آموزان)' : 'همشاگردی معلم';
}
