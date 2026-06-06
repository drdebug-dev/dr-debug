# Dr. Debug — Smart Error Log (سند معماری و طراحی — نسخه‌ی اصلاح‌شده v2)

> سند مرجع (master spec) برای پلاگین مدیریت و نمایش لاگ‌های وردپرس.
> پیشوند داخلی: `drdbg` · برند: Dr. Debug / دکتر دیباگ
> وضعیت: طراحی — قبل از پیاده‌سازی · این سند با پیشرفت کار به‌روز می‌شود.
> تغییرات نسخه‌ی ۲: رفع تناقضات، افزودن سناریوهای لبه‌ای، تکمیل بخش‌های ناقص.

---

## ۱. مسئله‌ای که حل می‌کنیم

فایل `debug.log` وردپرس به سه دلیل تجربه‌ی بدی می‌سازد:

1. **بدون ساختار است** — Notice، Warning، Fatal، Deprecated و stack trace همه پشت سر هم به‌صورت متن خام ریخته می‌شوند.
2. **به کاربر نشت می‌کند** — وقتی `WP_DEBUG_DISPLAY` روشن است، خطاها روی فرانت‌اند به همه‌ی بازدیدکننده‌ها دیده‌می‌شوند (مشکل امنیتی و تجربه‌کاربری).
3. **context ندارد** — نمی‌دانیم خطا در کدام URL، با چه کاربری، در چه نوع درخواستی (AJAX/REST/Cron) رخ داده است.

**هدف محصول:** فقط ادمین‌ها بتوانند لاگ‌ها را ببینند، با جزئیات بالا، در یک رابط مرتب — و هیچ خطایی به کاربر عادی نشت نکند.

---

## ۲. تصمیم معماری اصلی: مدل هیبرید

از سه گزینه (فایل‌محور / handler اختصاصی / هیبرید)، **هیبرید** انتخاب شد:

- `debug.log` خام به‌عنوان **خروجی استاندارد PHP** باقی می‌ماند — منبع مرجع برای دیدن خط‌به‌خط. چرخش فایل (بخش ۱۱) روی **کپی‌های آرشیوی** انجام می‌شود، نه روی فایل فعال؛ فایل فعال (`debug.log`) تا زمان چرخش دست‌نخورده است و پس از هر چرخش، فایل جدید جایگزین می‌شود.
- یک جدول دیتابیس فقط داده‌ی **ساختاریافته و قابل‌جستجو** نگه می‌دارد: fatalها، خطاهای گروه‌بندی‌شده با شمارنده، و context اضافه.
- **Redaction همزمان** روی هر دو خروجی اعمال می‌شود (بخش ۹) تا داده‌ی حساس نه در دیتابیس و نه در فایل خام ذخیره شود.

این‌گونه دیتابیس از روز اول لاغر می‌ماند چون قرار نیست هر Notice تکراری در آن بنشیند.

> **توضیح اصلاحی v2:** در نسخه‌ی قبلی نوشته شده بود «debug.log خام دست‌نخورده باقی می‌ماند» و همزمان «چرخش فایل» تعریف شده بود. این تناقض‌دار بود. در این نسخه مشخص شد: فایل فعال دست‌نخورده است تا زمانی که چرخش رخ دهد؛ چرخش یک عملیات دوره‌ای است که فایل را آرشیو و فایل خالی جدید می‌سازد. همچنین redaction قبل از رسیدن به debug.log اعمال می‌شود.

---

## ۳. اسکیمای دیتابیس (دو جدول)

برای حل تنشِ «dedup حجم را کم می‌کند ولی context هر رخداد را از دست می‌دهی»، از دو جدول با رابطه‌ی والد–فرزند استفاده می‌کنیم.

### ۳.۱. جدول گروه‌ها — `{prefix}drdbg_errors`

هر خطای یکتا = یک ردیف، با شمارنده.

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK | کلید اصلی |
| `fingerprint` | CHAR(64), **UNIQUE** | کلید insert-or-update — الگوریتم: SHA-256 (تغییر از SHA-1 به SHA-256 برای مقاومت بهتر در برابر collision) |
| `error_type` | SMALLINT | ثابت PHP (E_ERROR=1، E_WARNING=2، …) |
| `severity_level` | TINYINT | سطح مرتب‌شده برای UI (fatal=1 / error=2 / warning=3 / notice=4 / info=5) |
| `message` | TEXT | نمونه‌ی خام آخرین رخداد (بعد از redaction) |
| `message_normalized` | TEXT | مبنای گروه‌بندی (بعد از نرمال‌سازی) |
| `file` | VARCHAR(512) | مسیر فایل |
| `line` | INT UNSIGNED | از آخرین رخداد |
| `source_type` | TINYINT | plugin / theme / core / mu-plugin / unknown |
| `source_slug` | VARCHAR(191) | slug دقیق پلاگین/قالب مقصر |
| `count` | INT UNSIGNED | تعداد دفعات |
| `first_seen` | DATETIME | اولین مشاهده |
| `last_seen` | DATETIME | آخرین مشاهده |
| `status` | TINYINT | new=1 / seen=2 / resolved=3 / muted=4 |
| `resolved_at` | DATETIME NULL | زمان marking resolved (برای منطق بازگشت — بخش ۶.۴) |
| `resolved_by` | BIGINT UNSIGNED NULL | ID کاربری که resolved کرده |

**ایندکس‌ها:** `UNIQUE(fingerprint)` · `INDEX(last_seen)` · `INDEX(severity_level)` · `INDEX(status)` · `INDEX(source_slug)` · `INDEX(source_type)`
**جستجوی متنی:** به‌جای `FULLTEXT(message)` (که سربار InnoDB و محدودیت token-size دارد)، از **جستجوی سمت اپلیکیشن** استفاده شود: `LIKE` برای جستجوی ساده + امکان اضافه‌کردن FULLTEXT در آینده برای کاربران حرفه‌ای (قابل فعال‌سازی از تنظیمات).

> **توضیح اصلاحی v2:** (۱) `fingerprint` از `CHAR(40)` به `CHAR(64)` تغییر کرد (SHA-1 → SHA-256). (۲) `source_type` مقدار `mu-plugin` اضافه شد. (۳) ستون‌های `resolved_at` و `resolved_by` برای منطق بازگشت وضعیت اضافه شدند. (۴) FULLTEXT به‌عنوان پیش‌فرض حذف شد و جستجوی سمت اپلیکیشن جایگزین شد.

### ۳.۲. جدول رخدادها — `{prefix}drdbg_occurrences`

context تک‌تک دفعات، با **ring buffer** (فقط N رخداد آخر برای هر گروه).

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK | کلید اصلی |
| `error_id` | BIGINT UNSIGNED | ارجاع منطقی به `drdbg_errors.id` (FK منطقی، نه constraint) |
| `occurred_at` | DATETIME | زمان رخداد |
| `request_url` | VARCHAR(2048) | URL درخواست (بعد از redaction) |
| `request_method` | VARCHAR(10) | GET/POST/… |
| `context_type` | TINYINT | web=1 / ajax=2 / rest=3 / cron=4 / cli=5 |
| `user_id` | BIGINT UNSIGNED | 0 برای مهمان (موقع نمایش resolve شود؛ کاربر ممکن است حذف شده باشد) |
| `ip_hash` | CHAR(64) NULL | IP خام ذخیره نشود — SHA-256 با salt (بخش ۳.۴) |
| `memory_peak` | INT UNSIGNED | مصرف حافظه‌ی لحظه‌ی خطا |
| `php_version` | VARCHAR(20) | نسخه PHP |
| `wp_version` | VARCHAR(20) | نسخه وردپرس |
| `stack_trace` | LONGTEXT | trace قابل‌جمع‌شدن (collapsible) — بعد از redaction |
| `extra` | LONGTEXT | JSON-encoded برای داده‌ی متفرقه (فقط فیلدهایی که نیاز به فیلتر/مرتب‌سازی ندارند) |

**ایندکس:** ترکیبی `(error_id, occurred_at)` — هم برای واکشی رخدادهای یک گروه، هم برای حذف قدیمی‌ترین در ring buffer.

> چرا `extra` از نوع LONGTEXT است نه JSON بومی؟ چون `dbDelta` با ستون JSON مشکل دارد و باید سازگاری با MariaDB و نسخه‌های قدیمی MySQL حفظ شود. پرکاربردترین فیلدها ستون واقعی، بقیه JSON-encoded در LONGTEXT. **فیلدهایی که ممکن است نیاز به فیلتر/مرتب‌سازی داشته باشند هرگز در `extra` قرار نگیرند** — آن‌ها باید ستون واقعی داشته باشند.

### ۳.۳. نکات سطح‌دیتابیس

- ساخت جدول اولیه با `dbDelta()` (وسواسی است: دو فاصله بعد از `PRIMARY KEY`، هر فیلد یک خط، بدون FOREIGN KEY واقعی).
- **Migration:** برای تغییرات schema در نسخه‌های بعدی، `dbDelta()` فقط برای ساخت اولیه استفاده شود. برای migration از **دستورات SQL صریح** در تابع `drdbg_migrate()` استفاده شود که بر اساس `drdbg_db_version` شماره‌گذاری شده اجرا می‌شود. هر migration یک تابع مستقل باشد (مثل `drdbg_migrate_2_to_3()`) تا بتوان rollback نیز پیاده کرد.
- charset/collation با `$wpdb->get_charset_collate()` و `utf8mb4` (برای فارسی و کاراکترهای خاص/ایموجی در پیام‌ها).
- **نسخه‌بندی schema:** آپشن `drdbg_db_version` در `wp_options`؛ هر بار لود، نسخه‌ی کد با نسخه‌ی ذخیره‌شده مقایسه و در صورت تفاوت migration اجرا شود. از همان نسخه‌ی اول قرار داده شود.
- **حداقل نسخه‌ها:** MySQL 5.6+ (برای utf8mb4 و InnoDB FULLTEXT اگر فعال شد) / MariaDB 10.0+ / PHP 7.4+ / WordPress 5.2+ (برای `WP_Fatal_Error_Handler`).

### ۳.۴. هش IP با Salt

`ip_hash` به‌تنهایی ناامن است چون فضای آدرس‌های IP کوچک است و با brute-force قابل بازگشت است.

**راه‌حل:**
- Salt از `AUTH_SALT` (ثابت وردپرس در `wp-config.php`) استفاده شود: `ip_hash = hash('sha256', AUTH_SALT . $ip_address)`
- اگر `AUTH_SALT` تعریف نشده بود، از `DB_PASSWORD` به‌عنوان fallback استفاده شود.
- ذخیره‌ی IP اختیاری باشد و بتوان از تنظیمات آن را کاملاً غیرفعال کرد (بدون ذخیره‌ی هیچ اطلاعاتی از IP).

> **توضیح اصلاحی v2:** در نسخه‌ی قبلی IP بدون salt هش می‌شد که با توجه به فضای کوچک آدرس‌های IP عملاً رمزنگاری ضعیفی بود و مشکل GDPR/حریم خصوصی داشت.

---

## ۴. هسته‌ی dedup: نرمال‌سازی fingerprint

پیام خطاهای PHP معمولاً داده‌ی متغیر دارند (`"user_42"` ↔ `"user_91"`). hash مستقیم پیام خام = هزاران گروه تکراری.

**راه‌حل:** قبل از hash، پیام را نرمال کن:
1. اعداد منفرد → `#` (اما اعداد بخشی از شناسه‌ی معنادار مثل کلاس‌ها حفظ شوند)
2. رشته‌های داخل کوتیشن → `?`
3. آدرس‌های hex (مثل `0x7f...`) → حذف
4. **مسیرهای آپلود متغیر:** الگوی `/uploads/YYYY/MM/` → `/uploads/*/*/`
5. **مسیرهای موقت:** `/tmp/xxx/` → `/tmp/*/`
6. **ID عددی در نام کلاس/تابع حفظ شود:** چون `Class 'WooCommerce_42' not found` و `Class 'WooCommerce_91' not found` خطاهای متفاوتی هستند، نه یکسان. فقط اعداد مستقل (نه بخشی از identifier) جایگزین شوند.

سپس `fingerprint = SHA-256(message_normalized + file)`.

> نکته‌ی ظریف: **`line` در fingerprint نباشد.** با هر ویرایش کد شماره‌خط جابه‌جا می‌شود و یک خطای واحد را بین نسخه‌ها تکه‌تکه می‌کند. `line` فقط attribute آخرین رخداد است.

**هشدار ادغام اشتباه:** اگر دو خطای متفاوت با پیام مشابه در یک فایل (مثلاً دو `undefined variable` در خط ۵۰ و ۲۰۰) وجود داشته باشد، fingerprint یکسانی می‌سازند. برای کاهش این مشکل:
- در UI، وقتی کاربر یک گروه را باز می‌کند، **همه‌ی خطوط یکتا** (از `occurrences`) نمایش داده شود تا ببیند آیا واقعاً یک خطاست یا چند خطای ادغام‌شده.
- **اختیاری (Pro):** اضافه‌کردن `line_range` به fingerprint — مثلاً bucket خط (خط ۱-۵۰، ۵۱-۱۰۰، …) به‌جای خط دقیق، تا هم ویرایش‌های کوچک باعث تکه‌تکه شدن نشود و هم خطاهای خیلی دور ادغام نشوند.

> **توضیح اصلاحی v2:** نرمال‌سازی نسخه‌ی قبلی بیش از حد ساده بود و باعث می‌شد (۱) خطاهای کاملاً متفاوت با نام کلاس‌های متغیر اشتباهاً ادغام شوند، (۲) مسیرهای آپلود متغیر fingerprintهای متفاوت بسازند، و (۳) دو خطای متفاوت در یک فایل با پیام مشابه ادغام شوند.

---

## ۵. لایه‌ی ضبط (Capture)

### ۵.۱. سه مکانیزم جدا — یک handler کافی نیست

| مکانیزم | چه چیزی را می‌گیرد |
|---|---|
| `set_error_handler` | Warning, Notice, Deprecated, E_USER_* — **fatal را نمی‌گیرد** |
| `set_exception_handler` | exceptionهای catch-نشده |
| `register_shutdown_function` + `error_get_last()` | تنها راه گرفتن fatalها (E_ERROR, E_PARSE, E_COMPILE_ERROR) — **بدون stack trace** |

### ۵.۲. ترفند همزیستی هیبرید + زنجیره‌ی هندلرها

`set_error_handler` هندلر پیش‌فرض را جایگزین می‌کند. برای حفظ `debug.log` و سازگاری با سایر پلاگین‌ها:
- **ذخیره‌ی هندلر قبلی:** قبل از ثبت هندلر خود، `set_error_handler` قبلی را با `set_error_handler(function(){})` بگیر و در یک متغیر ذخیره کن. سپس هندلر خود را ثبت کن. در پایان هندلر خود، اگر هندلر قبلی وجود داشت آن را هم فراخوانی کن.
- در پایان هندلر **`return false`** بده تا pipeline استاندارد PHP هم اجرا شود (که `WP_DEBUG_LOG` روی آن سوار است).
- همان ابتدای هندلر: `if (!(error_reporting() & $errno)) return false;` تا اپراتور `@` و سطح فعلی error_reporting رعایت شود.
- **ترتیب بارگذاری:** mu-plugin زودتر از پلاگین‌های عادی لود می‌شود، پس هندلر ما اول ثبت می‌شود. پلاگین‌های بعدی (مثل Query Monitor) هندلر خود را ثبت می‌کنند و هندلر ما را در `set_error_handler` قبلی ذخیره می‌کنند. این رفتار طبیعی PHP است و مشکلی ایجاد نمی‌کند.

### ۵.۳. زمان‌بندی لود و هماهنگی با WP_Fatal_Error_Handler

- هندلرها را در یک **mu-plugin loader** کوچک ثبت کن تا زودتر از پلاگین‌های عادی اجرا شوند و خطاهای لودشدن بقیه را هم بگیری.
- **هماهنگی با وردپرس ۵.۲+:** وردپرس خودش `WP_Fatal_Error_Handler` دارد که در `register_shutdown_function` اجرا می‌شود. برای هماهنگی:
  1. پلاگین ما **نباید** `WP_Fatal_Error_Handler` را غیرفعال کند (`wp_fatal_error_handler_enabled` را `false` نکند).
  2. پلاگین ما یک shutdown function جداگانه با **اولویت بالاتر** (عدد کوچک‌تر) ثبت می‌کند تا قبل از `WP_Fatal_Error_Handler` اجرا شود: `register_shutdown_function([$this, 'handle_shutdown'])` به‌علاوه `add_action('shutdown', [$this, 'handle_shutdown'], -1)` برای hook وردپرس.
  3. در shutdown handler خود، `error_get_last()` را بخواند و اگر fatal بود، در بافر بنویسد. سپس اجرا به `WP_Fatal_Error_Handler` وردپرس برسد تا صفحه‌ی خطای استاندارد خود را نمایش دهد.
  4. برای drop-in اختصاصی (`fatal-error-handler.php`) فقط در **فاز Pro** استفاده شود و باید کلاس `WP_Fatal_Error_Handler` را extend کند تا رفتار استاندارد حفظ شود.

### ۵.۴. نکات ضبط درست

- backtrace را با `debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)` بگیر (آرگومان‌های توابع ممکن است داده‌ی حساس داشته باشند).
- برای fatalها trace در دسترس نیست — در UI شفاف بگو «برای این نوع خطا trace موجود نیست»، نه نمایش خالی.
- **فیلتر severity قبل از بافر:** اگر آستانه‌ی تنظیم‌شده `Warning` باشد، `Notice` و `Deprecated` اصلاً وارد بافر نشوند (نه اینکه بافر شوند و بعد هنگام نوشتن فیلتر شوند). این کار از اشغال بی‌مورد حافظه جلوگیری می‌کند.

> **توضیح اصلاحی v2:** (۱) مکانیزم chaining هندلرها اضافه شد تا با Query Monitor و Sentry سازگار باشد. (۲) نحوه هماهنگی با `WP_Fatal_Error_Handler` وردپرس ۵.۲+ مشخص شد. (۳) فیلتر severity قبل از بافر اعمال شود.

---

## ۶. منطق نوشتن (Write pipeline)

**اصل کلیدی عملکرد:** هیچ‌وقت روی هر Notice یک کوئری همزمان نزن. خطاها را در حافظه جمع کن و یک‌بار موقع shutdown دسته‌ای (batch) بنویس.

مسیر کامل:

```
[خطا رخ می‌دهد]
   ↓ (هر سه مکانیزم)
[فیلتر severity]  ← آستانه تنظیم‌شده؛ Notice/Deprecated اگر غیرفعال، اینجا حذف
   ↓
[بافر در حافظه]  ←── جمع + dedup در همان درخواست
   ↓                  └─→ return false ─→ Redaction ─→ debug.log (نسخه فیلترشده)
[shutdown]
   ↓
[Redact secrets]      ← پاک‌سازی توکن/پسورد/کلید (اگر قبلاً در write به فایل انجام نشده)
   ↓
[Build fingerprint + source detection]
   ↓
[Batch write to DB]
   ├─→ drdbg_errors      (insert یا count++ روی fingerprint)
   └─→ drdbg_occurrences (append + ring buffer trim)
   ↓
[اگر نوشتن DB ناموفق بود]
   └─→ نوشتن در فایل fallback: wp-content/drdbg-fallback.log
```

دو سود بافر: فشار دیتابیس به یک نوشتن در هر درخواست کاهش می‌یابد، و warningِ تکرارشده در همان درخواست dedup می‌شود.

### ۶.۱. سقف حافظه‌ی بافر

- **حداکثر تعداد ورودی در بافر:** ۱۰۰ خطا (قابل تنظیم). اگر از این حد عبور کند، خطاهای با severity کمتر (notice < warning < error < fatal) حذف شوند.
- **حداکثر حجم حافظه:** تخمین ۲ کیلوبایت به‌ازای هر ورودی ≈ ۲۰۰ کیلوبایت برای ۱۰۰ ورودی. اگر `memory_get_usage()` نشان داد که به `memory_limit` نزدیک می‌شویم (بیش از ۸۰٪)، بافر فوراً فلش شود.

### ۶.۲. Fallback هنگام عدم دسترسی به دیتابیس

اگر نوشتن در دیتابیس در shutdown ناموفق باشد (مثلاً MySQL down، connection timeout):
1. خطاهای بافر به‌صورت JSON در فایل `wp-content/drdbg-fallback.log` نوشته شوند.
2. در درخواست بعدی که دیتابیس در دسترس باشد، پلاگین محتوای fallback log را بخواند و در دیتابیس وارد کند.
3. اگر خود fallback هم ناموفق بود (مثلاً disk full)، فقط رویداد در سیستم log وردپرس (`error_log()`) ثبت شود.

### ۶.۳. Ring buffer (اصلاح‌شده)

- روش: **حذف batch** — هنگام افزودن رخداد جدید، فقط INSERT انجام شود. وقتی تعداد رخدادهای یک `error_id` از N (مثلاً ۲۰) بیشتر شد، **در یک عملیات دوره‌ای (موقع batch write)** قدیمی‌ترین‌ها حذف شوند:
  ```sql
  DELETE FROM {prefix}drdbg_occurrences
  WHERE error_id = %d AND id NOT IN (
      SELECT id FROM (
          SELECT id FROM {prefix}drdbg_occurrences
          WHERE error_id = %d ORDER BY occurred_at DESC LIMIT %d
      ) AS keep
  )
  ```
- این‌روش به‌جای یک DELETE به‌ازای هر INSERT، حذف‌ها را در یک کوئری batch انجام می‌دهد و فشار دیتابیس کمتر است.

### ۶.۴. منطق وضعیت resolved (جدید)

وقتی خطایی که `status = resolved` دارد دوباره رخ دهد:
1. `status` به `new` بازگردد (چون خطا هنوز حل نشده است).
2. `count++` انجام شود.
3. `resolved_at` و `resolved_by` **حفظ شوند** (برای گزارش‌دهی: «این خطا X بار بعد از resolved شدن دوباره رخ داده»).
4. ستون جدید `recurrence_count` INT UNSIGNED DEFAULT 0 اضافه شود — هر بار که resolved→new اتفاق بیفتد، این شمارنده یک واحد افزایش یابد.
5. در UI یک badge ویژه «recurrent» نمایش داده شود.

> **توضیح اصلاحی v2:** (۱) سقف حافظه‌ی بافر اضافه شد. (۲) Fallback برای زمان DB-down اضافه شد. (۳) Ring buffer از per-insert به batch تغییر کرد. (۴) منطق resolved→new تعریف شد.

---

## ۷. کنترل دسترسی و مخفی‌سازی از کاربر

- روی فرانت‌اند و برای غیرادمین‌ها: `display_errors=0` و override کردن `WP_DEBUG_DISPLAY` — هیچ خطایی روی صفحه نشت نکند.
- نمایش لاگ‌ها پشت `current_user_can('manage_options')` یا یک capability اختصاصی (`drdbg_view_logs`) قفل شود.
- نشانگر در admin bar (فقط برای ادمین) با badge تعداد خطاهای جدید.
- ضبط پشت‌صحنه همیشه کامل انجام می‌شود؛ فقط نمایش فرانت خاموش است.
- **REST API authentication:** تمام endpoint‌های پلاگین باید از `REST_COOKIE_INVALID_NONCE` و `current_user_can('drdbg_view_logs')` استفاده کنند. `wp_ajax_` هم با nonce و capability check محافظت شود.

---

## ۸. لایه‌ی دفاعی (پلاگین نباید خودش سایت را بخواباند)

1. **Reentrancy guard** — فلگ استاتیک تا اگر هندلر خودش خطا داد، حلقه‌ی بی‌نهایت نسازد.
2. **try/catch** دور تمام منطق داخل هندلر.
3. **Kill switch** — ثابت در `wp-config`: `define('DRDBG_DISABLE', true)` برای خاموش‌کردن بدون دسترسی به پنل. هنگام فعال‌بودن، mu-plugin هندلری ثبت نمی‌کند.
4. **DB error guard** — اگر خود نوشتن در دیتابیس باعث خطا شود، reentrancy guard از loop جلوگیری کند و خطا به fallback log برود (بخش ۶.۲).

---

## ۹. Redaction (قبل از نوشتن — در هر دو خروجی)

پاک‌سازی داده‌ی حساس **قبل از نوشتن در دیتابیس و قبل از رسیدن به debug.log** (نه موقع نمایش — وگرنه یک‌بار خام ذخیره می‌شود و نشت کرده).

- در `message`، `stack_trace`، `request_url` (پارامترهایی مثل `?token=`) و `extra`.
- مبنای پاک‌سازی: هم الگوی مقدار (pattern) و هم نام کلید (password, token, api_key, secret, authorization, …).
- **نحوه‌ی اعمال:** هندلر error، قبل از `return false` (که باعث نوشتن در debug.log می‌شود)، redaction را روی پیام اعمال کند. برای این‌کار باید از **custom error log handler** استفاده شود:
  - اگر `WP_DEBUG_LOG` مسیر یک فایل باشد، `ini_set('error_log', custom_path)` یا ثبت یک `error_log` handler اختصاصی که redaction را قبل از نوشتن اعمال کند.
  - **محدودیت:** PHP اجازه نمی‌دهد `error_log` handler سفارشی برای خطاهای استاندارد ثبت کرد. بنابراین راه‌حل عملی:
    1. خطا در بافر (بعد از redaction) ذخیره شود.
    2. `return false` اجرا شود تا PHP خطای اصلی (بدون redaction) در debug.log بنویسد.
    3. **یک WP-Cron job یا shutdown task** دوره‌ای `debug.log` را اسکن کند و الگوهای حساس شناخته‌شده را redact کند (best-effort، نه ضمانت‌دار).
  - **توصیه‌ی امنیتی:** در مستندات پلاگین صراحتاً ذکر شود: «داده‌ی حساس ممکن است به‌صورت موقت در `debug.log` خام وجود داشته باشد. پیشنهاد می‌کنیم `debug.log` را از وب‌سرور قابل دسترسی نباشید (محافظت با `.htaccess` یا nginx config). دیتابیس Dr. Debug داده‌ی حساس ذخیره نمی‌کند.»

> **توضیح اصلاحی v2:** در نسخه‌ی قبلی redaction فقط قبل از نوشتن در DB ذکر شده بود، اما `debug.log` خام بدون فیلتر باقی می‌ماند. این یک نشت امنیتی بود. در این نسخه مشکل به‌صورت شفاف ذکر و راه‌حل best-effort ارائه شده.

---

## ۱۰. تشخیص منبع (Source detection)

مسیر فایل با مسیرهای شناخته‌شده مقایسه می‌شود تا مقصر مشخص شود:

### ۱۰.۱. الگوریتم تشخیص

```
مسیر فایل خطا
  ↓
آیا در WP_PLUGIN_DIR است؟ → source_type = plugin
  ↓ نه
آیا در wp-content/mu-plugins است؟ → source_type = mu-plugin
  ↓ نه
آیا مسیر قالب فعال (get_stylesheet_directory()) است؟ → source_type = theme
  ↓ نه
آیا در wp-includes است؟ → source_type = core
  ↓ نه
آیا در ABSPATH/wp-admin است؟ → source_type = core
  ↓ نه
source_type = unknown
```

### ۱۰.۲. استخراج slug دقیق

- **برای plugin:** اولین پوشه/فایل بعد از `WP_PLUGIN_DIR/` slug است. مثلاً `/wp-content/plugins/woocommerce/includes/class-wc.php` → slug = `woocommerce`. اگر فایل مستقیماً در پوشه‌ی plugins باشد (بدون پوشه)، نام فایل بدون `.php` slug است.
- **برای mu-plugin:** نام فایل بعد از `mu-plugins/` — با این تفاوت که mu-plugin‌ها می‌توانند در پوشه‌ی فرعی باشند.
- **برای theme:** `get_stylesheet()` (slug قالب فعال).
- **برای core:** slug = `wordpress`.
- **تطبیق با ساختار Bedrock:** اگر `WP_PLUGIN_DIR` تعریف نشده یا مسیر غیراستاندارد دارد، از تابع `plugin_dir_path()` و مقایسه‌ی نسبی استفاده شود. ثابت `DRDBG_PLUGIN_DIR_OVERRIDE` برای سازگاری با ساختارهای خاص تعریف شود.

> **توضیح اصلاحی v2:** (۱) mu-plugin به‌عنوان source_type اضافه شد. (۲) الگوریتم استخراج slug دقیقاً تعریف شد. (۳) سازگاری با Bedrock و ساختارهای غیراستاندارد اضافه شد.

---

## ۱۱. استراتژی پاک‌سازی (چند لایه)

| لایه | شرح | یادداشت |
|---|---|---|
| زمان‌محور | حذف entryهای قدیمی‌تر از X روز با WP-Cron | بازه قابل‌تنظیم؛ WP-Cron فقط با بازدید اجرا می‌شود |
| حجم‌محور | سقف ردیف/مگابایت؛ FIFO حذف قدیمی‌ترین‌ها | مطمئن‌تر چون مستقیم به حجم گره خورده |
| چرخش فایل | log rotation روی `debug.log`؛ آرشیو gzip، نگهداری N آرشیو آخر | فایل فعال تا زمان چرخش دست‌نخورده است؛ چرخش فایل فعال را آرشیو و فایل خالی جدید می‌سازد |
| دستی | دکمه‌ی «پاک کردن همه» و «پاک کردن resolvedها» | برای تمیزکاری دستی ادمین |

### ۱۱.۱. بهبود WP-Cron

WP-Cron فقط با بازدید اجرا می‌شود و روی سایت کم‌بازدید قابل‌اتکا نیست:
- پلاگین در activation، یک WP-Cron event با `wp_schedule_event()` ثبت کند.
- در هر اجرای cron، بعد از پاک‌سازی، `wp_remote_post()` به خود سایت بزند تا اجرای بعدی تضمین شود (self-triggering).
- در تنظیمات، گزینه‌ای برای **لغو WP-Cron و استفاده از real cron** اضافه شود با دستورالعمل `crontab -e` برای کاربر.
- سقف اجرای cron: حداکثر ۱۰۰۰ ردیف در هر اجرا حذف شود (برای جلوگیری از timeout).

> **توضیح اصلاحی v2:** تناقض «debug.log دست‌نخورده» و «چرخش فایل» رفع شد. راه‌حل عملی برای WP-Cron اضافه شد.

---

## ۱۲. تنظیمات

در `wp_options` نگهداری می‌شوند (حجم کم، نیازی به جدول مستقل نیست) — شامل:

| کلید | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `drdbg_severity_threshold` | int | 3 (Warning) | حداقل severity برای ضبط در DB |
| `drdbg_retention_days` | int | 30 | روز نگهداری لاگ |
| `drdbg_max_rows` | int | 10000 | سقف ردیف در drdbg_errors |
| `drdbg_ring_buffer_size` | int | 20 | تعداد رخداد نگهداری‌شده برای هر گروه |
| `drdbg_buffer_max_entries` | int | 100 | سقف ورودی‌های بافر حافظه |
| `drdbg_mute_patterns` | array | [] | الگوهای mute (regex) |
| `drdbg_collect_ip` | bool | false | آیا IP هش‌شده ذخیره شود |
| `drdbg_redaction_enabled` | bool | true | فعال‌بودن redaction |
| `drdbg_pro_key` | string | '' | کلید لایسنس Pro |
| `drdbg_db_version` | string | '1.0' | نسخه‌ی schema دیتابیس |

---

## ۱۳. تصمیم‌های قفل‌شده

- معماری: **هیبرید** (debug.log خام + دیتابیس ساختاریافته).
- **دو جدول**: گروه‌ها + رخدادها.
- dedup با **fingerprint نرمال‌شده (SHA-256)**؛ `line` خارج از fingerprint.
- Ring buffer با **حذف batch**، N ≈ ۲۰.
- `severity_level` **جدا** از `error_type` (برای sort/filter مرتب در UI).
- نوشتن **batch در shutdown**، نه لحظه‌ای، با **fallback log**.
- `return false` برای حفظ `debug.log`.
- ثبت هندلرها در **mu-plugin loader** با **chaining هندلر قبلی**.
- **هماهنگی با WP_Fatal_Error_Handler** بدون غیرفعال‌کردن آن.
- آستانه‌ی دیتابیس: **Warning به بالا** پیش‌فرض؛ Notice/Deprecated اختیاری — **فیلتر قبل از بافر**.
- تشخیص منبع: **الگوریتم مشخص** + پشتیبانی از mu-plugin و Bedrock.
- تنظیمات و الگوهای mute در **wp_options**.
- نسخه‌بندی schema از **همان نسخه‌ی اول** + **migration صریح** (نه فقط dbDelta).
- Redaction **قبل از نوشتن** (در هر دو خروجی — best-effort برای debug.log).
- محافظ‌ها: reentrancy guard + try/catch + kill switch + DB error guard.
- **سقف حافظه‌ی بافر** فعال.
- **منطق resolved→new** با `recurrence_count`.
- **IP hash با salt**.

---

## ۱۴. موارد باز / فازهای بعدی

- **نوتیف تلگرام/بله برای fatal** — موکول به فاز Pro؛ نقطه‌ی اتصال (shutdown handler + drop-in fatal) همین حالا در طراحی هست.
- **توضیح خطا با AI** — دکمه‌ی «این خطا یعنی چی و چطور حلش کنم» (هویت «دکتر دیباگ»).
- **RTL کامل + تاریخ جلالی** — امضای بازار ایران.
- **Multi-site** — برای نسخه‌ی Pro. در نسخه‌ی Free: پلاگین فقط در محیط single-site فعال شود؛ در multisite یک notice نمایش دهد.
- گروه‌بندی/شمارنده، فیلتر و جستجو، نمودار روند خطا، mark as resolved / mute، live tail.
- **FULLTEXT index** — به‌عنوان آپشن قابل‌فعال‌سازی در تنظیمات (برای کاربران حرفه‌ای با جداول بزرگ).

---

## ۱۵. مدل درآمدی (پیشنهادی)

- **رایگان (wp.org):** ویوئر پایه + خاموش‌کردن نمایش فرانت — برای جذب کاربر.
- **Pro (CodeCanyon / ژاکت):** AI، تلگرام، ذخیره‌ی دیتابیسی پیشرفته، multi-site، RTL/جلالی، FULLTEXT.

---

## ۱۶. REST API و ارتباط UI با بک‌اند (جدید)

### ۱۶.۱. Endpoint‌ها

| متد | مسیر | توضیح |
|---|---|---|
| GET | `drdbg/v1/errors` | لیست خطاها با فیلتر/صفحه‌بندی |
| GET | `drdbg/v1/errors/{id}` | جزئیات یک خطا + رخدادها |
| POST | `drdbg/v1/errors/{id}/resolve` | mark as resolved |
| POST | `drdbg/v1/errors/{id}/mute` | mark as muted |
| DELETE | `drdbg/v1/errors` | حذف دسته‌ای (resolved / همه) |
| GET | `drdbg/v1/stats` | آمار کلی (تعداد بر اساس severity، روند) |
| GET | `drdbg/v1/settings` | خواندن تنظیمات |
| POST | `drdbg/v1/settings` | ذخیره‌ی تنظیمات |

### ۱۶.۲. احراز هویت

- تمام endpoint‌ها با `permission_callback => function() { return current_user_can('drdbg_view_logs'); }` محافظت شوند.
- nonce validation برای AJAX requests.
- rate limiting برای جلوگیری از abuse.

---

## ۱۷. رفتار Deactivation و Uninstall (جدید)

### ۱۷.۱. Deactivation

- mu-plugin loader هندلرها را **unregister** کند: `restore_error_handler()` و `restore_exception_handler()`.
- WP-Cron event‌ها حذف شوند (`wp_clear_scheduled_hook`).
- جداول دیتابیس و تنظیمات **حفظ شوند** (کاربر ممکن است دوباره فعال کند).
- admin bar indicator حذف شود.

### ۱۷.۲. Uninstall (uninstall.php)

- **گزینه‌ی کاربر:** در صفحه‌ی uninstall، چک‌باکس «حذف تمام داده‌ها» ارائه شود.
  - اگر تیک خورد: جداول `drdbg_errors` و `drdbg_occurrences` حذف شوند + تمام `wp_options` با پیشوند `drdbg_` حذف شوند + فایل fallback log حذف شود.
  - اگر تیک نخورد: فقط تنظیمات `wp_options` حذف شوند؛ جداول نگه داشته شوند.
- mu-plugin loader (اگر قابل‌نوشتن باشد) حذف شود.

---

## ۱۸. استراتژی i18n (جدید)

- text domain: `dr-debug`
- فایل‌های ترجمه در `languages/` با فرمت `.l10n.php` (وردپرس ۶.۵+) و `.po/.mo` (سازگاری با نسخه‌های قدیمی).
- اولویت ترجمه: انگلیسی (پایه) → فارسی → سایر زبان‌ها بر اساس تقاضا.
- تاریخ جلالی و RTL فقط در فاز Pro.

---

## ۱۹. استراتژی تست (جدید)

- **واحد‌تست:** PHPUnit برای منطق نرمال‌سازی fingerprint، redaction، source detection، و resolved logic.
- **تست یکپارچگی:** تست واقعی با خطاهای PHP در محیط وردپرس (WP_TestCase).
- **تست عملکرد:** benchmark سربار هندلرها — سقف: کمتر از ۱ms اضافه بر هر درخواست.
- **CI:** GitHub Actions با ماتریس PHP 7.4/8.x + WordPress 5.2/latest + MySQL 5.7/8.0 + MariaDB 10.x.
- حداقل **پوشش ۸۰٪** برای منطق هسته (fingerprint، redaction، buffer).

---

## ۲۰. سربار عملکرد تخمینی (جدید)

| عملیات | سربار تخمینی | یادداشت |
|---|---|---|
| error handler per error | < 0.1ms | فقط جمع در بافر |
| redaction per error | < 0.05ms | regex ساده |
| fingerprint per error | < 0.05ms | SHA-256 + normalization |
| batch write (shutdown) | 1-5ms | بسته به تعداد خطاها |
| admin bar badge query | < 1ms | با ایندکس روی status+severity |
| memory overhead | ~200KB | بافر حداکثر ۱۰۰ ورودی |

---

## ۲۱. قدم‌های بعدی

1. نهایی‌کردن نام تجاری محصول و slug.
2. طراحی UI/UX پنل ادمین (مرحله‌ی آخر طبق توافق).
3. تعریف ساختار پوشه‌ها و mu-plugin loader.
4. پیاده‌سازی لایه‌ی capture + write (پس از تأیید طراحی).
5. نوشتن واحد‌تست‌های هسته (fingerprint، redaction، buffer).
6. تست یکپارچگی در محیط‌های مختلف (single-site، Bedrock، PHP 7.4/8.x).

---

## الف. خلاصه‌ی تغییرات v2 نسبت به v1

| # | مشکل | شدت | تغییر اعمال‌شده |
|---|---|---|---|
| ۱ | تناقض «debug.log دست‌نخورده» و «چرخش فایل» | بحرانی | توضیح شد: فایل فعال تا چرخش دست‌نخورده؛ چرخش عملیات دوره‌ای |
| ۲ | از دست رفتن خطاها در shutdown (DB down) | بحرانی | Fallback log + سقف حافظه‌ی بافر + DB error guard |
| ۳ | تداخل با WP_Fatal_Error_Handler | بحرانی | مکانیزم هماهنگی مشخص شد (بدون غیرفعال‌کردن) |
| ۴ | IP hash بدون salt | مهم | Salt از AUTH_SALT + گزینه‌ی غیرفعال‌سازی |
| ۵ | Redaction فقط روی DB، نه debug.log | مهم | Redaction در هر دو خروجی + توصیه‌ی امنیتی |
| ۶ | منطق resolved مشخص نیست | مهم | resolved→new با recurrence_count + resolved_at/by |
| ۷ | نرمال‌سازی fingerprint ساده‌اندیشانه | مهم | قوانین دقیق‌تر + هشدار ادغام اشتباه + bucket خط (Pro) |
| ۸ | تداخل هندلر با سایر پلاگین‌ها | مهم | Chaining هندلر قبلی |
| ۹ | Source detection ناقص | مهم | الگوریتم مشخص + mu-plugin + Bedrock |
| ۱۰ | Ring buffer per-insert حذف | متوسط | تغییر به batch trim |
| ۱۱ | FULLTEXT سربار دارد | متوسط | حذف از پیش‌فرض + آپشن Pro |
| ۱۲ | dbDelta برای migration محدود | متوسط | Migration صریح + تابع مستقل |
| ۱۳ | فیلتر severity بعد از بافر | متوسط | فیلتر قبل از بافر |
| ۱۴ | WP-Cron غیرقابل‌اتکا | متوسط | Self-triggering + گزینه‌ی real cron |
| ۱۵ | عدم ذکر Uninstall/Deactivation | متوسط | بخش ۱۷ اضافه شد |
| ۱۶ | REST API مشخص نشده | گمشده | بخش ۱۶ اضافه شد |
| ۱۷ | حداقل نسخه‌ها مشخص نیست | گمشده | بخش ۳.۳ اضافه شد |
| ۱۸ | استراتژی i18n نیست | گمشده | بخش ۱۸ اضافه شد |
| ۱۹ | استراتژی تست نیست | گمشده | بخش ۱۹ اضافه شد |
| ۲۰ | سربار عملکرد تخمینی نیست | گمشده | بخش ۲۰ اضافه شد |
| ۲۱ | fingerprint از SHA-1 ضعیف | متوسط | SHA-256 |
