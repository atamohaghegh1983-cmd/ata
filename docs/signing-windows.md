# ساخت کلید امضای اپ «همشاگردی» در ویندوز

این کار **یک بار** انجام می‌شود (حدود ۱۵ دقیقه). بعد از آن:
- هر APK تازه بدون حذف نسخه‌ی قبلی، روی آن نصب می‌شود؛
- می‌توانید اپ را در کافه‌بازار و مایکت منتشر کنید.

> ⚠️ فایل کلید (`hamshagerdi.jks`) و رمزش را **هرگز گم نکنید** و برای هیچ‌کس (حتی پشتیبان یا Claude) نفرستید.
> اگر گم شود، دیگر نمی‌توانید نسخه‌ی جدید اپ را در بازار منتشر کنید.
> یک نسخه روی فلش و یک نسخه در جای امن دیگر نگه دارید.

---

## قدم ۱ — نصب Java (رایگان)
1. به سایت **adoptium.net** بروید (اگر باز نشد، از فیلترشکن یا سایت‌های آینه‌ی ایرانی مثل «سافت98» نسخه‌ی **Temurin JDK 17** را بگیرید).
2. نسخه‌ی **JDK 17 — Windows x64 — .msi** را دانلود و نصب کنید.
3. در مراحل نصب، گزینه‌ی **Set JAVA_HOME** و **Add to PATH** را روشن کنید.

**آزمایش:** منوی Start ← بنویسید `PowerShell` ← باز کنید ← این را بنویسید و Enter بزنید:
```powershell
keytool -help
```
اگر متن راهنما آمد، Java درست نصب شده است. اگر خطای «not recognized» آمد، یک بار کامپیوتر را خاموش و روشن کنید و دوباره امتحان کنید.

## قدم ۲ — ساخت کلید
در همان PowerShell، اول به پوشه‌ی «اسناد» بروید:
```powershell
cd $HOME\Documents
```
سپس این دستور را (یک خط) بزنید:
```powershell
keytool -genkeypair -v -keystore hamshagerdi.jks -storetype PKCS12 -keyalg RSA -keysize 2048 -validity 10000 -alias hamshagerdi
```
سؤال‌هایی می‌پرسد:
- **Enter keystore password**: یک رمز قوی (دست‌کم ۸ نویسه، فقط حروف و عدد انگلیسی) بنویسید و Enter بزنید. موقع تایپ چیزی نمایش داده نمی‌شود؛ طبیعی است.
- **Re-enter**: همان رمز را دوباره.
- نام و نام خانوادگی، واحد، سازمان، شهر، استان: هر چه می‌خواهید (به انگلیسی)؛ کشور: `IR`.
- **Is … correct?** بنویسید `yes`.

فایل `hamshagerdi.jks` در پوشه‌ی «اسناد» ساخته می‌شود. **رمز را همین حالا جایی امن یادداشت کنید.**

## قدم ۳ — تبدیل کلید به متن (برای گیت‌هاب)
```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("$HOME\Documents\hamshagerdi.jks")) | Set-Content "$HOME\Documents\hamshagerdi-base64.txt"
```
فایل `hamshagerdi-base64.txt` ساخته می‌شود (یک متن طولانی).

## قدم ۴ — گذاشتن در GitHub Secrets
1. در مرورگر مخزن `atamohaghegh1983-cmd/ata` را باز کنید.
2. **Settings** ← در ستون چپ **Secrets and variables** ← **Actions** ← دکمه‌ی سبز **New repository secret**.
3. این چهار مورد را یکی‌یکی بسازید (نام دقیقاً همین باشد):

| Name | Secret (مقدار) |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | کل متن فایل `hamshagerdi-base64.txt` (با Notepad باز کنید، Ctrl+A، Ctrl+C) |
| `ANDROID_KEYSTORE_PASSWORD` | رمزی که در قدم ۲ گذاشتید |
| `ANDROID_KEY_ALIAS` | `hamshagerdi` |
| `ANDROID_KEY_PASSWORD` | همان رمز قدم ۲ |

4. بعد از ساختن، فایل `hamshagerdi-base64.txt` را از کامپیوتر **پاک کنید** (خود `hamshagerdi.jks` را نگه دارید).

## قدم ۵ — ساخت APK با کلید جدید
1. در گیت‌هاب: **Actions** ← در ستون چپ **App APK** ← دکمه‌ی **Run workflow** ← شاخه‌ی `claude/affectionate-shannon-rcgbwa` ← **Run workflow**.
2. بعد از حدود ۱۰ دقیقه، در صفحه‌ی همان اجرا، در قسمت لاگ «Signing key» باید نوشته باشد: **✅ امضا با کلید شما**.
3. این بار **یک بار دیگر** اپ قبلی را از گوشی حذف کنید و APK جدید را نصب کنید.
   از این به بعد، همه‌ی نسخه‌های بعدی **بدون حذف** روی آن نصب می‌شوند.

---

### اگر جایی گیر کردید
- «keytool is not recognized»: Java نصب نشده یا PATH تنظیم نشده؛ قدم ۱ را دوباره انجام دهید (گزینه‌ی Add to PATH).
- در گیت‌هاب «Signing key» نوشته «کلید امضا در Secrets نیست»: نام Secretها را دقیقاً مثل جدول بنویسید (حروف بزرگ).
- ساخت APK با خطای «Keystore was tampered with, or password was incorrect» شکست خورد: رمز در `ANDROID_KEYSTORE_PASSWORD` یا `ANDROID_KEY_PASSWORD` با رمز کلید یکی نیست.
