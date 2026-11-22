# اپ همشاگردی (Flutter)

| پوشه | کار |
|---|---|
| `lib/core` | تنظیمات (`config.dart`)، رنگ و فونت، اتصال به API با توکن و رفرش خودکار، کش آفلاین، ابزار فارسی |
| `lib/features/auth` | معرفی، ورود با موبایل، کد ۵ رقمی |
| `lib/features/onboarding` | تکمیل ثبت‌نام |
| `lib/features/home` | خانه‌ی هر نقش |

آدرس سایت: `lib/core/config.dart` یا هنگام ساخت `--dart-define=HS_SITE=https://...`

```bash
flutter pub get
flutter analyze
flutter test
flutter build apk --release --split-per-abi
```

## اگر دانلودها در ایران گیر کرد
```bash
export PUB_HOSTED_URL=https://pub.flutter-io.cn
export FLUTTER_STORAGE_BASE_URL=https://storage.flutter-io.cn
```
برای Gradle، یک بار قبل از ساخت APK:
```bash
mkdir -p ~/.gradle && cp android/init.gradle.aliyun ~/.gradle/init.gradle
```

## آزمون‌های اختیاری
- `HS_SHOTS=1 flutter test test/screenshots_test.dart --update-goldens` ← تصویر صفحه‌ها در `build/shots`
- `HS_E2E_SITE=https://staging... HS_E2E_OTP_CMD='...' flutter test test/e2e` ← آزمون سرتاسری روی سایت آزمایشی
