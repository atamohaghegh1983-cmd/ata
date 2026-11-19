# مستندات API همشاگردی (نسخه‌ی ۲.۶.۰)

پایه: `https://SITE/wp-json/hamshagerdi/v1` (یا `https://SITE/?rest_route=/hamshagerdi/v1/...`).
همه‌ی پاسخ‌ها JSON است. خطا: `{ "code", "message", "data": { "status", ... } }` و `message` همیشه فارسی و قابل نمایش به کاربر است.
همه‌ی تاریخ‌ها میلادی `YYYY-MM-DD` هستند؛ تبدیل به شمسی در اپ انجام می‌شود. ارقام فارسی در ورودی پذیرفته می‌شود.

## نیازمندی‌ها
| افزونه | حداقل نسخه | برای |
|---|---|---|
| مدیریت کلاس | 1.7.3 | همه‌ی مسیرها |
| کاربران و عضویت | 2.6.3 | ورود پیامکی، تکمیل ثبت‌نام، کد کلاس |
| ساختار مدیریت مدرسه | 2.2.1 | مدرسه و نظارت مدیر |
| پنل والدین | 1.1.3 | مجوز بخش‌ها برای والدین |
| پنل دانش‌آموز | 1.1.0 | رمز ۴ رقمی و کارت کلاس |
| مقالات | 1.1.0 | مجله |

## قواعد دسترسی (در سرور بررسی می‌شود)
- **معلم**: فقط کلاس‌هایی که معلم آن است؛ نوشتن (`access: write`).
- **مدیر/ناظر مدرسه**: فقط کلاس‌های مدرسه‌ی خودش، فقط‌خواندنی (`access: super`)، فقط اگر مدیر کل سایت تأییدش کرده باشد.
- **والد**: فقط فرزندان متصل خودش. هر بخش (حضور، امتیاز، تکلیف، اطلاعیه، پیام…) جداگانه با مجوزهای «پنل والدین» بررسی می‌شود؛ بخش بسته در پاسخ نمی‌آید و نامش در `locked` می‌آید (یا خطای ۴۰۳ با `code: hs_feature_locked` و `data.feature`).
- **دانش‌آموز**: فقط خودش و کلاس‌های خودش (`access: student`).
- **حساب مدیریتی سایت** (مدیر/ویراستار) از اپ وارد نمی‌شود. حساب **تعلیق‌شده** هم وارد نمی‌شود و توکن‌هایش باطل است.

## احراز هویت
توکن دسترسی: ۱ ساعت، هدر `Authorization: Bearer …` (یا `X-HS-Authorization: Bearer …` اگر سرور هدر Authorization را حذف کند).
توکن رفرش: ۳۰ روز، حداکثر ۵ دستگاه، با هر رفرش عوض می‌شود (توکن قبلی باطل).
تغییر رمز، تعلیق، رد عضویت، حذف کاربر یا «خروج از همه» ← همه‌ی توکن‌ها باطل.
توکن این API فقط روی مسیرهای `/hamshagerdi/v1` پذیرفته می‌شود.

| متد | مسیر | بدنه | پاسخ / توضیح |
|---|---|---|---|
| POST | `/auth/otp/request` | `mobile` | `{ok, mobile, wait, ttl, length}` — کد ۵ رقمی با پیامک (Pinova). فاصله‌ی ارسال دوباره ۶۰ ثانیه (۴۲۹)، حداکثر ۵ ارسال در ساعت برای هر شماره و ۱۵ برای هر IP. وجود یا نبود حساب فاش نمی‌شود. |
| POST | `/auth/otp/verify` | `mobile, code, device` | جفت توکن + `user` + `is_new` + `needs_onboarding`. شماره‌ی جدید ← حساب ساخته می‌شود. ۵ کد اشتباه ← کد باطل (۴۲۹)؛ سه دور ← قفل یک‌ساعته. پس از ورود `hsum_mobile_known` اجرا می‌شود (اتصال خودکار والد با شماره، دعوت کادر). شماره‌ی حساب دانش‌آموز ← ۴۰۹ `hs_student_account`. |
| POST | `/auth/login` | `username, password, device` | ورود با رمز (سازگاری). ۵ تلاش ناموفق برای هر حساب (از هر IP) یا ۲۰ برای هر IP ← ۱۵ دقیقه قفل (۴۲۹). |
| POST | `/auth/refresh` | `refresh_token, device?` | جفت توکن جدید. |
| POST | `/auth/logout` | `refresh_token?`, `all?`, `device_token?` | خروج از این دستگاه / همه‌ی دستگاه‌ها؛ `device_token` توکن اعلان این گوشی را هم پاک می‌کند. |

## حساب
| متد | مسیر | توضیح |
|---|---|---|
| GET | `/me` | `id, name, first_name, last_name, mobile, view (teacher/manager/parent/student/other), roles, status (active/pending/rejected/suspended), avatar, can_teach, has_children, can_supervise, needs_onboarding, school?, unread_messages`. |
| GET | `/onboarding` | `needs, status, roles[], relations, provinces[], school_types, staff_positions, data` (اطلاعات ذخیره‌شده‌ی قبلی). |
| POST | `/onboarding` | همان فیلدهای فرم سایت (`HSUM_Onboard::process`). پاسخ `{ok, message, role, user}`. خطا ۴۰۰ با پیام فارسی. |
| POST | `/devices` | `token` (۱۶ تا ۵۱۲ نویسه: حروف، رقم، `_-:.`)، `platform` (android/ios/web)، `provider` (fcm/pushe/najva/other)، `app_version`. هر توکن فقط به یک حساب تعلق دارد. |
| DELETE | `/devices` | `token` |

### فیلدهای POST /onboarding
- مشترک: `role` = `parent` \| `teacher` \| `school_admin`، `first_name`, `last_name`, `city`.
- والد: `relation` (father/mother/parent)، `father_first/last/mobile`، `mother_first/last/mobile`، `child_first`, `child_last`, `child_city`, `province`, `school_name`, `teacher_name`, `teacher_mobile`, `code` (کد کلاس یا محتوای QR)، `kids[]` = `{first, last, school, teacher, teacher_mobile, code}`.
- معلم: `school_name_t`, `subject`, `manager_name_t`, `manager_mobile_t`, `school_address_t`.
- مدیر مدرسه (نیاز به تأیید): `personnel_code`, `school_name_m`, `school_type`, `province_m`, `city_m`, `school_address_m`, `postal_code_m` (۱۰ رقم)، `school_phone`, `school_code`, `staff[]` = `{position, first, last, mobile, subject?, class?, grade?}`. دعوت کادر **بعد از تأیید** مدیر کل ساخته می‌شود.

## کلاس‌ها
`access` در هر کلاس: `write` | `super` | `student` | `parent`.

| متد | مسیر | دسترسی | توضیح |
|---|---|---|---|
| GET | `/classes` | همه | کلاس‌های قابل‌دسترس: `id, title, grade, teacher, teacher_id, students, access, sort` |
| GET | `/classes/options` | همه | `can_create, grades[], provinces[], year, defaults{school, city, province}` |
| POST | `/classes` | معلم | `title, grade, school, province, city, description?` ← ۲۰۱ `{message, class}`. حداکثر ۲۰ کلاس در روز. |
| GET | `/classes/{id}` | همه‌ی سطوح | یک کلاس |
| GET | `/classes/{id}/students` | معلم، ناظر | `id, name, first_name, last_name, avatar, has_photo` (به ترتیب انتخابی معلم) |
| POST | `/classes/{id}/students` | معلم | `first_name, last_name, parent_name, parent_phone, class_number, birth_date` ← `id, username, password, parent_code` (تکراری ← ۴۰۹) |
| GET | `/classes/{id}/students/{sid}` | معلم، ناظر | پروفایل سریع: `points_total, points_recent[], attendance_month, parents[]` |
| GET | `/classes/{id}/attendance?date=` | معلم، ناظر | `date, recorded, records[{student_id, name, avatar, status, note}]` |
| POST | `/classes/{id}/attendance` | معلم | `date, records[{student_id, status: present/absent/late/excused, note}]`. تاریخ حداکثر ۱ روز آینده و ۴۰۰ روز گذشته. دانش‌آموز کلاس دیگر نادیده گرفته می‌شود (`skipped`). |
| GET | `/classes/{id}/attendance/week?from=` | معلم، ناظر | ۷ روز: `days[{date, recorded}]` (نوار روزهای هفته) |
| GET | `/classes/{id}/homework` | همه | معلم/ناظر: `summary`؛ دانش‌آموز/والد: `my_status{student_id: {status, feedback}}`. والد فقط اگر بخش «تکالیف» باز باشد. |
| POST | `/classes/{id}/homework` | معلم | `title, description, subject, due_date` ← ۲۰۱ |
| GET/POST | `/homework/{id}/statuses` | GET: معلم/ناظر، POST: معلم | `records[{student_id, status: pending/done/partial/missing, feedback}]` |
| GET | `/classes/{id}/announcements` | همه | والد فقط اگر «اطلاعیه‌ها» باز باشد |
| POST | `/classes/{id}/announcements` | معلم | `title, body, pinned` ← ۲۰۱ |
| GET | `/classes/{id}/points` | معلم، ناظر | `students[{…, total}]`, `leaderboard`, `behaviors[{id, name, icon, points, positive}]` |
| POST | `/classes/{id}/points` | معلم | `behavior_id, student_ids[], note` ← `awarded, points` |
| GET | `/classes/{id}/invite` | معلم | `parent{code, url, qr, expires_at}` (QR دعوت والدین) و `student_card{code, url, qr, login_enabled}` (QR کارت ورود دانش‌آموز) |
| POST | `/classes/{id}/invite` | معلم | `which: parent | student_card` ← کد تازه (قبلی باطل)؛ حداکثر ۱۰ بار در ساعت |

## والدین
همه‌ی مسیرهای `/children/{id}…` فقط برای والدِ متصل همان فرزند است (معلم و مدیر هم رد می‌شوند: ۴۰۳).

| متد | مسیر | توضیح |
|---|---|---|
| GET | `/children` | `id, name, first_name, avatar, relation, pin_login, classes[{id, title, grade, teacher, teacher_id, features{…: bool}}]` |
| GET | `/children/{id}` | به ازای هر کلاس: `attendance_month`، `points`، `homework[]` (هر کدام فقط اگر باز باشد) + `locked[]` + `features` |
| GET | `/children/{id}/today?date=` | خلاصه‌ی امروز: `attendance{status, note}`، `points_today`، `points_items[]`، `points_total`، `homework_open[]`، `announcements[]` (۳ تای آخر)، `can_message`، `locked[]` |
| GET | `/children/{id}/week?from=` | گزارش ۷ روز: `days[{date, attendance, points}]` |
| POST | `/children/join` | `code`: **کد اتصال والد** (۸ نویسه، مخصوص هر دانش‌آموز) ← اتصال فوری. یا کد کلاس/لینک `/join/CODE`/متن QR همراه `child_name` ← `status: linked | request`. اگر شماره‌ی شما در پرونده‌ی دانش‌آموز ثبت باشد فوراً وصل می‌شوید؛ وگرنه درخواست برای معلم می‌رود. ۸ کد اشتباه ← ۱۵ دقیقه قفل. |
| GET | `/children/proposals` | پیشنهادهای اتصال (معلم شماره‌ی شما را ثبت کرده): `id, student, class, teacher, relation` |
| POST | `/children/proposals/{id}` | `accept: true/false` ← تأیید یا رد |
| GET | `/children/requests` | درخواست‌های اتصالِ در انتظار تأیید معلم |
| GET | `/children/{id}/pin` | `enabled, has_pin` (خود رمز هرگز برنمی‌گردد) |
| POST | `/children/{id}/pin` | `pin` (۴ رقم؛ رمزهای ساده مثل 1234 و 0000 پذیرفته نمی‌شود) ← ورود فرزند با کارت کلاس + این رمز فعال می‌شود |
| DELETE | `/children/{id}/pin` | بستن ورود فرزند با رمز |

## پیام‌ها
فقط معلم ↔ والد/دانش‌آموزِ کلاس خودش. والد فقط اگر بخش «پیام به معلم» باز باشد می‌تواند بفرستد. گفت‌وگوی رابطه‌ی قطع‌شده دیده نمی‌شود.

| متد | مسیر | توضیح |
|---|---|---|
| GET | `/messages` | صندوق: `user_id, name, avatar, preview, at, unread, can_reply` |
| GET | `/messages/{user_id}` | `can_reply, messages[{id, mine, body, image, at}]` (خوانده‌شده علامت می‌خورد) |
| POST | `/messages` | `recipient_id, body` (حداکثر ۲۰۰۰ نویسه)، `class_id?` ← ۲۰۱. حداکثر ۳۰ پیام در ۵ دقیقه. |

## داشبورد
| متد | مسیر | توضیح |
|---|---|---|
| GET | `/dashboard` | `role, date, unread_messages` و بسته به نقش: `teacher{totals, classes[], parent_requests}`، `parent{children[], announcements[], proposals}`، `manager{school, is_manager, totals, classes[], teachers[], pending_invites, task_counts}` |

## دانش‌آموز
| متد | مسیر | توضیح |
|---|---|---|
| POST | `/auth/kid/class` | `code` (کد یا لینک QR کارت کلاس) ← `class, students[{id, name, avatar, locked}]` فقط دانش‌آموزانی که ورودشان باز است. ۲۰ کد اشتباه در ساعت برای هر IP. |
| POST | `/auth/kid/login` | `code, student_id, pin, device` ← جفت توکن. ۵ رمز اشتباه ← ۱۵ دقیقه قفل؛ ۱۵ اشتباه در روز ← قفل تا فردا. |
| GET | `/student/summary` | فقط دانش‌آموز: کلاس‌ها، امتیاز کل و اخیر، تکالیف با وضعیت خودش |

## حذف
| متد | مسیر | دسترسی | توضیح |
|---|---|---|---|
| DELETE | `/children/{id}` | والدِ همان فرزند | جدا کردن فرزند از حساب خودش؛ والد دیگر و سوابق می‌ماند |
| DELETE | `/classes/{id}/students/{sid}` | معلم همان کلاس | بیرون کردن از کلاس؛ حساب، سوابق و اتصال والدین می‌ماند |
| DELETE | `/school/staff/{id}` | مدیر تأییدشده‌ی همان مدرسه | حذف معلم/معاون از کادر (`id` = شناسه‌ی ردیف کادر در `/school`)؛ عضویت از راه دعوت هم قطع می‌شود؛ حساب و کلاس‌های او می‌ماند |

## مجله
| متد | مسیر | توضیح |
|---|---|---|
| GET | `/articles?page=&per_page=&search=&audience=&grade=&mine=1` | `items[{id, title, excerpt, date, image, audiences[], grades[], locked, lock{reason, message}, link}]`, `total, pages`. `mine=1` = «مطالب پایه‌ی من». |
| GET | `/articles/{id}` | باز: `content` (HTML کامل). قفل: `content: ""` و `teaser` کوتاه + `lock.reason` (login / audience / grade / nograde / password). |
| GET | `/articles/filters` | `audiences, grades, my{role, audiences, grades}` |

## مدرسه
| متد | مسیر | توضیح |
|---|---|---|
| GET | `/school` | ناظر/مدیر: اطلاعات کامل + `classes[]`؛ مدیر: + `staff[]` و `invites[]`. عضو کادر بدون اجازه‌ی نظارت: فقط `id, name`. |
| GET | `/school/tasks` | مدیر: همه؛ بقیه: وظایف خودشان |
| POST | `/school/tasks/{id}/status` | `status, report` (فقط مسئول انجام) |

## تازه‌های ۲.۶.۰ (نیاز به «مدیریت کلاس» ۱.۷.۵)

### برنامه‌ی هفتگی و زنگ‌بندی
| روش | مسیر | توضیح |
|---|---|---|
| GET | `/classes/{id}/timetable` | `{bells{days,periods,slots,day_names,source}, cells[{day,period,subject,teacher_id,teacher}], can_edit, teachers, score}` — معلم کلاس، مدیر و ناظر مدرسه، دانش‌آموزان و والدینشان |
| POST | `/classes/{id}/timetable` | `{cells:[{day,period,subject,teacher_id}]}` کل برنامه جایگزین می‌شود. تداخل معلم ← 409 `hs_timetable_conflict` با `data.conflicts` و هیچ چیز ذخیره نمی‌شود |
| POST | `/classes/{id}/bells` | `{days, slots:[{type: class|break, start, end}]}` یا `{reset:true}` |
| GET/POST | `/school/bells` | زنگ‌بندی مدرسه (فقط مدیر) |
| GET | `/me/timetable` | معلم: زنگ‌های خودش در همه‌ی کلاس‌ها؛ دانش‌آموز: کلاس خودش؛ والد: `?child=ID` |

روزها: ۰ = شنبه. `score`: امتیاز ۰ تا ۱۰۰ با `checks` (تداخل، زنگ خالی، تعادل روزانه، پراکندگی، استراحت معلم).

### پیام
- هر پیام در گفت‌وگو: `receipt` = `sent | delivered | read`، `delivered_at`، `read_at`، `edited`، `can_edit`.
- «رسید» وقتی ثبت می‌شود که اپ گیرنده صندوق، گفت‌وگو یا داشبورد را بگیرد؛ «خوانده شد» با باز کردن گفت‌وگو.
- `POST /messages/item/{id}` `{body}` ویرایش (فقط فرستنده، تا ۲۴ ساعت).

### دیوار کلاس
`POST /portfolio/{id}/pin {on}` سنجاق (معلم کلاس)؛ پست سنجاق‌شده بالای دیوار و `pinned: true`.
`GET /teacher/feed?class_id=` دیوار و اطلاعیه‌های اخیر برای داشبورد معلم.

### ترتیب دانش‌آموزان
`POST /classes/{id}/students/order {mode: first|last|custom, order:[ids]}`.

### بودجه‌بندی
`GET /classes/{id}/budget` حالا `suggestions` (دروس پایه یا فهرست مدرسه)، `mine` (منابع معلم)، `units` و `unit` هر ردیف را دارد.
`POST /me/budget-sources {sources:[{name,unit,total}]}`؛ مدیر: `GET/POST /school/budget-subjects?grade=N`.

### تقویم
`GET /calendar?tasks=1` برای معلم موعد تکالیف و ارزیابی‌ها را هم در `tasks` می‌دهد.

### نسخه‌ی اپ
`GET /app/latest` (بدون ورود) ← `{available, version, build, url, size, notes, min_build}`. بارگذاری فایل در پیشخوان: تنظیمات ← «اپ موبایل»؛ دکمه‌ی دانلود: `[hamshagerdi_app_download]`.

## تازه‌های ۲.۵.۰ (نیاز به «مدیریت کلاس» ۱.۷.۴)

### اطلاعیه با مدت نمایش
`POST /classes/{id}/announcements` و `POST /announcements/{id}` فیلدهای `show_from` و `show_until` (YYYY-MM-DD) می‌گیرند. والد و دانش‌آموز فقط اطلاعیه‌های در بازه را می‌بینند؛ معلم و مدیر همه را با `state` = `active | scheduled | archived`.

### تکلیف با پیوست
| روش | مسیر | توضیح |
|---|---|---|
| POST | `/uploads` | multipart فیلد `file` (عکس، PDF، Word، PowerPoint، Excel، صدا، ویدیو؛ تا ۱۵ مگابایت؛ فقط معلم) ← `{url,name,type}` |
| POST | `/classes/{id}/homework` | فیلد تازه‌ی `attachments: [{url,name,type}]` (حداکثر ۵، فقط http/https) |

فهرست تکالیف (معلم، والد، دانش‌آموز) فیلد `attachments` دارد.

### ارزیابی و نمرات
| روش | مسیر | توضیح |
|---|---|---|
| GET/POST | `/classes/{id}/assessments` | فهرست / ساخت `{title, subject, scale: descriptive|numeric, date}` |
| GET | `/assessments/{id}` | دانش‌آموزان با نتیجه |
| POST | `/assessments/{id}/results` | `{results: {student_id: {level, score, comment}}}` (نمره ۰ تا ۱۰۰) |
| DELETE | `/assessments/{id}` | حذف |

### بودجه‌بندی
`GET/POST /classes/{id}/budget` با `rows: [{subject, planned, done}]`؛ پاسخ `expected` (درصد گذشته از سال) و وضعیت هر درس.

### تقویم آموزشی و مناسبتی
| روش | مسیر | توضیح |
|---|---|---|
| GET | `/calendar?from=&to=` | رویدادهای قابل‌دیدن کاربر (+ تقویم مناسبتی سایت اگر نصب باشد) |
| POST | `/calendar` | معلم: `{title, date, end_date?, type, class_id}`؛ مدیر مدرسه: `{title, date, type, level?, audience}` برای کل مدرسه |
| DELETE | `/calendar/{id}` | سازنده، معلم کلاس یا مدیر مدرسه |
| GET | `/children/{id}/calendar` | تقویم فرزند (رویدادها با مقطع + موعد تکلیف، ارزیابی، جلسه) |

نوع‌ها: `academic` (تقویم آموزشی)، `cultural` (پرورشی و مناسبتی)، occasion، holiday، exam، meeting، ceremony، deadline. مقطع‌ها: preschool، primary، middle، high (از پایه‌ی کلاس تشخیص داده می‌شود).

### کتابخانه‌ی کاربرگ
`GET /worksheets?grade=&subject=&s=&page=` و `POST /worksheets/{id}/assign {class_id, due_date, note}` ← تکلیف با پیوست کاربرگ.

### پیام‌ها
- `GET /messages`: هر نفر یک ردیف با `unread_count` و `label` (مثل «والد سارا رضایی» یا «معلم کلاس سوم الف»).
- `GET /messages/{user}`: `partner {name,label}` و برای هر پیام `can_delete`.
- `DELETE /messages/item/{id}`: فرستنده، معلم کلاس، مدیر مدرسه، مدیر سایت، نماینده (دستیار) والدین کلاس.
- اعلان پیام‌های یک فرستنده در یک ردیف جمع می‌شود («۵ پیام جدید»)؛ پیام‌های فرستاده‌شده از سایت هم اعلان دارند. ارسال به گوشی بلافاصله بعد از پاسخ انجام می‌شود (نه با کران وردپرس).

### داشبوردها
- معلم: `month` (حضور این ماه)، `top` (برترین‌ها)، `upcoming` (تکالیف پیش‌رو)، `events`؛ و برای هر کلاس همین‌ها به‌اضافه‌ی `budget` و `grade`.
- والد: برای هر فرزند `hw_done/hw_total`، `stars`، `pending`، `events`، `classes`.
- مدیر: `grade` و `level` هر کلاس، `events`، `levels`.
- `GET /children/{id}/progress?y=&m=`: گزارش پیشرفت ماهانه (حضور، امتیاز، ارزیابی‌ها، تکالیف، تمرین).

## تازه‌های ۲.۴.۰

همه‌ی مسیرها زیر `/wp-json/hamshagerdi/v1` و با توکن ورود. دسترسی‌ها در سرور بررسی می‌شود.

### اطلاعیه
| روش | مسیر | توضیح |
|---|---|---|
| POST | `/announcements/{id}` | ویرایش `{title, body, pinned}` — نویسنده، معلم کلاس، مدیر مدرسه، مدیر سایت |
| DELETE | `/announcements/{id}` | حذف (همان افراد) |

فهرست اطلاعیه‌ها حالا فیلد `can_edit` دارد.

### اتصال والد با کد کلاس (یک کد برای کل کلاس)
| روش | مسیر | توضیح |
|---|---|---|
| POST | `/join/students` | `{code}` ← `{class, students:[{id,name,avatar,mine}]}` |
| POST | `/children/join-class` | `{code, student_id}` ← اگر موبایل والد در پرونده باشد `status=linked`، وگرنه `status=request` (درخواست برای معلم) |
| GET | `/requests` | درخواست‌های منتظر معلم با `candidates` |
| POST | `/requests/{id}` | `{approve: true/false, student_id}` |

در ورود پیامکی، والدی که معلم شماره‌اش را ثبت کرده خودکار وصل می‌شود (بدون تأیید).

### کلاس
| روش | مسیر | توضیح |
|---|---|---|
| GET | `/classes?mine=1` | فقط کلاس‌هایی که کاربر معلم آن است |
| POST | `/classes/{id}/students/import` | `{rows:[{first_name,last_name,parent_phone,parent_name,class_number}]}` حداکثر ۱۵۰ |
| POST | `/classes/{id}/broadcast` | `{body}` پیام خصوصی به همه‌ی والدین کلاس |
| POST | `/classes/{id}/students/{sid}/avatar` | عکس دانش‌آموز (multipart، فیلد `photo`) |
| GET | `/classes/{id}/wall` | دیوار کلاس (پست‌های تأییدشده) |
| GET | `/classes/{id}/portfolio` | همه‌ی پست‌های کلاس (فقط معلم) |

### پورتفولیو
| روش | مسیر | توضیح |
|---|---|---|
| GET/POST | `/portfolio/student/{id}` | دیدن / پست تازه (multipart: `title, text, photo`) — حداکثر ۲ پست در روز |
| POST | `/portfolio/student/{id}/about` | `{about}` معرفی کوتاه |
| POST | `/portfolio/{id}/wall` | `{on: true/false}` گذاشتن روی دیوار کلاس (معلم) |
| POST | `/portfolio/{id}/cheer` | تشویق ❤️ (دوباره زدن ← برداشتن) |
| DELETE | `/portfolio/{id}` | حذف |

پست‌ها اول فقط برای معلم و والدین همان دانش‌آموز دیده می‌شوند.

### عکس
`POST /children/{id}/avatar` (والد) و `POST /me/avatar` (خود کاربر) — multipart، فیلد `photo`، jpg/png/webp تا ۵ مگابایت.

### مدرسه
| روش | مسیر | توضیح |
|---|---|---|
| GET/POST | `/school/profile` | ثبت/ویرایش مدرسه توسط مدیر (حتی پیش از تأیید). کد مدرسه یکتا و معتبر بررسی می‌شود |
| POST | `/school/staff` | `{first,last,mobile,position}` افزودن معلم/معاون |
| GET | `/school/claims` | کلاس‌هایی که معلم‌ها برای اتصال فرستاده‌اند |
| POST | `/school/claims/{id}` | تأیید، یا `{reject:true}` |
| POST | `/me/school` | معلم: `{school_code}` یا `{manager_mobile}` ← بعد از تأیید مدیر وصل می‌شود |

### اعلان و حساب
| روش | مسیر | توضیح |
|---|---|---|
| GET | `/notifications` | `{unread, items}` |
| POST | `/notifications/read` | `{ids?}` بدون ids ← همه |
| POST | `/me/delete` | درخواست حذف حساب؛ همه‌ی نشست‌ها باطل می‌شود |

ارسال به گوشی با Firebase (FCM HTTP v1): فایل JSON حساب سرویس را در «تنظیمات ← اعلان اپ» وارد کنید (یا ثابت `HS_API_FCM_JSON` در wp-config). بدون آن، فقط مرکز اعلان داخل اپ کار می‌کند.

### نقش دانش‌آموز در تکمیل ثبت‌نام
`POST /onboarding` با `{role: "student", first_name, last_name, code}` (کد کلاس).

## نمونه
```bash
curl -X POST https://SITE/wp-json/hamshagerdi/v1/auth/otp/request -H 'Content-Type: application/json' -d '{"mobile":"09121112233"}'
curl -X POST https://SITE/wp-json/hamshagerdi/v1/auth/otp/verify  -H 'Content-Type: application/json' -d '{"mobile":"09121112233","code":"12345","device":"Galaxy A15"}'
curl https://SITE/wp-json/hamshagerdi/v1/children -H 'Authorization: Bearer ACCESS_TOKEN'
```

## نکته‌های نصب
- اگر Apache هدر Authorization را حذف کرد، در `.htaccess` اضافه کنید:
  `RewriteCond %{HTTP:Authorization} ^(.*)` و `RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]` (یا اپ از هدر `X-HS-Authorization` استفاده کند).
- کلید امضا (۳۲ بایت تصادفی) خودکار در پایگاه داده ساخته می‌شود؛ می‌توانید با ثابت `HS_API_JWT_SECRET` در `wp-config.php` جایگزین کنید. با عوض‌کردن آن همه باید دوباره وارد شوند.
- HTTPS الزامی است.
- «حالت آزمایشی» ورود پیامکی (تنظیمات ورود) باید روی سایت اصلی خاموش باشد؛ در حالت آزمایشی کد پیامک نمی‌شود و فقط در پیشخوان نمایش داده می‌شود.

## قلاب‌ها برای اعلان (مرحله‌ی ۶)
`hs_api_attendance_saved`، `hs_api_homework_created`، `hs_api_announcement_created`، `hs_api_points_awarded`، `hs_api_message_sent`، `hs_api_registered`، `hs_api_login`.
