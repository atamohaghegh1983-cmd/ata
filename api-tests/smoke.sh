#!/usr/bin/env bash
# آزمون سریع روی سایت واقعی/آزمایشی با curl (فقط‌خواندنی؛ جز یک پیامک ورود به شماره‌ی خودتان).
#   bash smoke.sh https://hamshagerdionline.ir 09xxxxxxxxx
set -u
SITE="${1:?آدرس سایت را بدهید}"; MOBILE="${2:?شماره‌ی موبایل خودتان}"
B="$SITE/wp-json/hamshagerdi/v1"
ok(){ printf '✅ %s\n' "$1"; }; bad(){ printf '❌ %s\n' "$1"; FAIL=1; }; FAIL=0
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }

[ "$(code "$SITE/wp-json/wp/v2/users")" != 200 ] && ok "فهرست کاربران وردپرس بسته است" || bad "/wp/v2/users هنوز باز است (شماره‌ها لو می‌رود)"
[ "$(code "$SITE/wp-json/wp/v2/hs_class")" != 200 ] && ok "فهرست کلاس‌ها در REST عمومی نیست" || bad "/wp/v2/hs_class هنوز باز است"
[ "$(code "$B/me")" = 401 ] && ok "بدون توکن ← ۴۰۱" || bad "/me بدون توکن ۴۰۱ نداد"
[ "$(code "$B/children/1")" = 401 ] && ok "/children بدون توکن بسته است" || bad "/children بدون توکن باز است"
[ "$(code -X POST "$B/auth/otp/request" -H 'Content-Type: application/json' -d '{"mobile":"123"}')" = 400 ] && ok "شماره‌ی نامعتبر رد شد" || bad "شماره‌ی نامعتبر رد نشد"

echo "— ارسال کد به $MOBILE …"
curl -s -X POST "$B/auth/otp/request" -H 'Content-Type: application/json' -d "{\"mobile\":\"$MOBILE\"}"; echo
read -r -p "کد ۵ رقمی پیامک‌شده: " OTP
R=$(curl -s -X POST "$B/auth/otp/verify" -H 'Content-Type: application/json' -d "{\"mobile\":\"$MOBILE\",\"code\":\"$OTP\",\"device\":\"smoke\"}")
TOK=$(printf '%s' "$R" | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')
RT=$(printf '%s' "$R" | sed -n 's/.*"refresh_token":"\([^"]*\)".*/\1/p')
[ -n "$TOK" ] && ok "ورود پیامکی و دریافت توکن" || { bad "ورود انجام نشد: $R"; exit 1; }
H="Authorization: Bearer $TOK"
for r in me classes children messages articles onboarding; do
  c=$(code "$B/$r" -H "$H"); [ "$c" = 200 ] && ok "GET /$r" || bad "GET /$r ← $c"
done
[ "$(code "$SITE/wp-json/wp/v2/users/1" -H "$H")" != 200 ] && ok "توکن اپ روی /wp/v2 کار نمی‌کند" || bad "توکن اپ روی /wp/v2 پذیرفته شد"
curl -s -o /dev/null -X POST "$B/auth/logout" -H "$H" -H 'Content-Type: application/json' -d "{\"refresh_token\":\"$RT\"}"
[ "$(code -X POST "$B/auth/refresh" -H 'Content-Type: application/json' -d "{\"refresh_token\":\"$RT\"}")" = 401 ] && ok "خروج، توکن رفرش را باطل کرد" || bad "توکن رفرش بعد از خروج هنوز کار می‌کند"
exit $FAIL
