# تست‌های API همشاگردی

## تست‌های خودکار (PHPUnit) — روی وردپرس آزمایشی، نه سایت اصلی
```bash
composer install
cp wp-tests-config-sample.php wp-tests-config.php   # پایگاه داده‌ی خالی و جدا بدهید؛ در هر اجرا پاک می‌شود
WP_CORE_DIR=/path/to/wordpress vendor/bin/phpunit
```
۴۵ تست: ورود پیامکی، JWT، ابطال توکن، سقف تلاش، دسترسی معلم/مدیر/والد/دانش‌آموز، مجوزهای والدین، پیام، کد کلاس، رمز فرزند، مجله، حریم خصوصی و رفع باگ‌های افزونه‌های هسته.

## آزمون سریع روی سایت (curl)
```bash
bash smoke.sh https://hamshagerdionline.ir 09xxxxxxxxx
```
یک پیامک ورود به شماره‌ی خودتان می‌فرستد و بقیه‌ی بررسی‌ها فقط‌خواندنی است.
