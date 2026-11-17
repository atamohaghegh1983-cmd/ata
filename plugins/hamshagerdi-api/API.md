# مستندات API همشاگردی (v1)

پایه: `https://SITE/wp-json/hamshagerdi/v1` — همه‌ی پاسخ‌ها JSON. خطاها: `{ "code", "message", "data": {"status"} }`.

## احراز هویت
| متد | مسیر | بدنه | توضیح |
|---|---|---|---|
| POST | `/auth/login` | `username, password, device` | ورود (نام کاربری = شماره موبایل؛ ارقام فارسی پذیرفته می‌شود). ۵ تلاش ناموفق ← قفل ۱۵ دقیقه‌ای (429) |
| POST | `/auth/refresh` | `refresh_token` | دریافت جفت توکن جدید؛ توکن قدیمی باطل می‌شود (چرخش) |
| POST | `/auth/logout` | `refresh_token` یا `all:true` | خروج از این دستگاه / همه‌ی دستگاه‌ها |
| GET | `/me` | — | پروفایل، نقش (`view`: teacher/manager/parent/student)، پیام‌های خوانده‌نشده |
| POST | `/devices` | `token, platform` | ثبت توکن دستگاه برای اعلان پوش (آینده) |

Access token: ۱ ساعت، هدر `Authorization: Bearer …`. Refresh token: ۳۰ روز، حداکثر ۵ دستگاه.
> اگر Apache هدر Authorization را حذف کرد، در `.htaccess` اضافه کنید:
> `RewriteCond %{HTTP:Authorization} ^(.*)` ← `RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]`  (یا از هدر `X-HS-Authorization` استفاده کنید)

## کلاس‌ها (سطح دسترسی در فیلد `access`: write | super | student | parent)
| متد | مسیر | دسترسی |
|---|---|---|
| GET | `/classes` | همه (فقط کلاس‌های قابل‌دسترس) |
| GET | `/classes/{id}/students` | معلم، مدیر/ناظر |
| POST | `/classes/{id}/students` | معلم — بدنه: `first_name, last_name, parent_name, parent_phone, class_number, birth_date`؛ تکراری ← ۴۰۹ |
| GET, POST | `/classes/{id}/attendance` (`?date=`; POST: `date, records[{student_id,status,note}]`) | GET: معلم/ناظر — POST: فقط معلم |
| GET, POST | `/classes/{id}/homework` (POST: `title, description, subject, due_date`) | GET: همه — POST: معلم |
| GET, POST | `/homework/{id}/statuses` (POST: `records[{student_id,status,feedback}]`) | GET: معلم/ناظر — POST: معلم |
| GET, POST | `/classes/{id}/announcements` (POST: `title, body, pinned`) | GET: همه — POST: معلم |
| GET, POST | `/classes/{id}/points` (POST: `behavior_id, student_ids[], note`) | GET: معلم/ناظر — POST: معلم |

## والدین
`GET /children`، `GET /children/{id}` (حضور ماه جاری، امتیاز، تکالیف)

## پیام‌ها (خصوصی؛ فقط معلم ↔ والد/دانش‌آموزِ کلاس خودش)
`GET /messages` (صندوق)، `GET /messages/{user_id}` (گفتگو)، `POST /messages` (`recipient_id, body`)

## مدرسه
`GET /school`، `GET /school/tasks`، `POST /school/tasks/{id}/status` (`status, report`؛ فقط مسئول انجام)

## نمونه
```bash
curl -X POST https://SITE/wp-json/hamshagerdi/v1/auth/login \
  -H 'Content-Type: application/json' -d '{"username":"09121112233","password":"…","device":"Pixel 7"}'
curl https://SITE/wp-json/hamshagerdi/v1/classes -H 'Authorization: Bearer ACCESS_TOKEN'
```

## امنیت
- کلید امضا (۳۲ بایت تصادفی) در گزینه‌ی غیرخودکار ذخیره می‌شود؛ می‌توانید با ثابت `HS_API_JWT_SECRET` در wp-config جایگزین کنید.
- مدیر مدرسه فقط‌خواندنی است (۴۰۳ روی هر نوشتن در امور کلاسی)، طبق مدل ناظر.
- هر عملیات نوشتن عضویت دانش‌آموز در همان کلاس را بررسی می‌کند. HTTPS الزامی است.
