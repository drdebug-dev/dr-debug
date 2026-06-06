# Dr. Debug — Smart Error Log Viewer for WordPress

**دکتر دیباگ** — A smart, privacy-aware error log viewer for WordPress with fingerprint-based grouping, secret redaction, and a modern Persian RTL admin dashboard.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Why Dr. Debug?

WordPress `debug.log` is useful for developers, but painful to work with:

- **Unstructured** — fatals, warnings, notices, and stack traces are dumped as raw text.
- **Leaks to visitors** — when `WP_DEBUG_DISPLAY` is on, errors can appear on the frontend.
- **No context** — you cannot easily see which URL, user, or request type triggered an error.

Dr. Debug replaces the "open a giant text file" workflow with a structured dashboard. Errors are captured automatically, grouped by fingerprint, enriched with request context, and shown only to administrators.

---

## Features

| Feature | Description |
|---|---|
| **Fingerprint grouping** | Identical errors are deduplicated using normalized SHA-256 fingerprints — you see patterns, not noise. |
| **Secret redaction** | Passwords, API keys, Bearer tokens, and similar values are stripped before storage. |
| **Source detection** | Automatically identifies whether an error comes from a plugin, theme, or WordPress core. |
| **Ring buffer** | Keeps a configurable number of recent occurrences per error group to prevent database bloat. |
| **Severity levels** | Fatal, Error, Warning, Notice, and Info — mapped from PHP error types. |
| **Status workflow** | Mark errors as New, Seen, Resolved, or Muted. |
| **Admin bar indicator** | See the count of new errors directly from the WordPress admin bar. |
| **Smart cleanup** | Time-based cleanup via WP-Cron, with configurable retention. |
| **Log rotation** | Automatically compresses and rotates `debug.log` when it exceeds 10 MB. |
| **MU-plugin loader** | Captures errors that happen before regular plugins load — including fatals during plugin initialization. |
| **Persian RTL UI** | Full right-to-left admin interface in Persian (فارسی). |
| **Demo data seeder** | Generate realistic sample errors to explore the dashboard. |
| **Multisite ready** | Network-aware; activate per site or network-wide. |

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.0+ (5.2+ recommended for fatal error handler) |
| PHP | 7.4 – 8.3+ |
| MySQL / MariaDB | MySQL 5.6+ or MariaDB 10.0+ |

Enable WordPress debug mode in `wp-config.php` for error capture to work:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false ); // Dr. Debug can hide frontend display
```

---

## Installation

### Option 1 — Upload via WordPress admin

1. Clone or download this repository.
2. Create a zip archive so the root folder inside the zip is named `dr-debug` and contains `dr-debug.php`:

   ```bash
   cd /path/to/parent
   zip -r dr-debug.zip dr-debug -x "*.git*" -x "*/docs/*"
   ```

3. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip, and install.
4. Activate the plugin.
5. Open **دکتر دیباگ** in the admin sidebar.

On activation, the plugin creates its database tables and copies the MU-plugin loader to `wp-content/mu-plugins/drdbg-loader.php`.

### Option 2 — Manual install

1. Copy the `dr-debug` folder to `wp-content/plugins/`.
2. Activate **Dr. Debug — Smart Error Log Viewer** from the Plugins screen.
3. Navigate to **دکتر دیباگ** in the admin menu.

### Try it with demo data

Click **داده نمونه** (Demo Data) in the dashboard to populate sample errors and explore the UI without triggering real failures.

---

## Configuration

Settings are available in the plugin dashboard under **تنظیمات** (Settings):

| Setting | Default | Description |
|---|---|---|
| Cleanup days | 30 | Delete occurrences older than N days |
| Max occurrences | 20 | Ring buffer size per error group |
| Min severity | Error | Minimum severity level to capture |
| Redaction | On | Strip sensitive values from messages and stack traces |
| Hide frontend | On | Prevent errors from displaying to site visitors |

### Kill switch

Add this to `wp-config.php` to disable capture instantly (useful during emergencies or migrations):

```php
define( 'DRDBG_DISABLE', true );
```

When enabled, the MU-plugin loader and capture engine stop registering handlers.

---

## How it works

Dr. Debug uses a **hybrid architecture**:

```
PHP errors / exceptions / fatals
        │
        ▼
  Capture layer (error handler + exception handler + shutdown)
        │
        ├──► debug.log (standard PHP log, with redaction)
        │
        └──► MySQL tables (structured, searchable data)
                  ├── wp_drdbg_errors      (grouped error patterns)
                  └── wp_drdbg_occurrences (recent context per group)
```

**Fingerprint algorithm:** error messages are normalized (variable numbers, quoted strings, upload paths, etc. are replaced with placeholders), then hashed with SHA-256 together with the file path. Line numbers are intentionally excluded from the fingerprint so code edits do not split the same error into multiple groups.

**Privacy:** client IP addresses are stored only as SHA-256 hashes salted with `AUTH_SALT` — never in plain text.

For the full architecture specification, see [`docs/dr-debug-log-viewer-spec-v2.md`](docs/dr-debug-log-viewer-spec-v2.md).

---

## REST API

All endpoints require the `manage_options` capability and are namespaced under `drdbg/v1`:

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/wp-json/drdbg/v1/stats` | Dashboard statistics |
| `GET` | `/wp-json/drdbg/v1/errors` | List errors (filterable, paginated) |
| `GET` | `/wp-json/drdbg/v1/errors/{id}` | Single error with occurrences |
| `PATCH` | `/wp-json/drdbg/v1/errors/{id}` | Update error status |
| `DELETE` | `/wp-json/drdbg/v1/errors/{id}` | Delete an error group |
| `GET` | `/wp-json/drdbg/v1/errors/{id}/occurrences` | Paginated occurrences |
| `POST` | `/wp-json/drdbg/v1/errors/batch` | Batch status update |
| `GET` / `POST` | `/wp-json/drdbg/v1/settings` | Read / save settings |
| `POST` | `/wp-json/drdbg/v1/cleanup` | Run manual cleanup |
| `POST` | `/wp-json/drdbg/v1/seed` | Seed demo data |

---

## Project structure

```
dr-debug/
├── dr-debug.php              # Main plugin bootstrap
├── uninstall.php             # Clean removal on plugin delete
├── readme.txt                # WordPress.org plugin readme
├── admin/
│   └── class-drdbg-admin.php # Admin menu, assets, admin bar
├── assets/
│   ├── css/drdbg-admin.css
│   └── js/drdbg-admin.js     # Dashboard SPA (Persian RTL)
├── includes/
│   ├── class-drdbg-activator.php
│   ├── class-drdbg-admin-api.php
│   ├── class-drdbg-capture.php
│   ├── class-drdbg-cleanup.php
│   ├── class-drdbg-constants.php
│   ├── class-drdbg-db.php
│   ├── class-drdbg-fingerprint.php
│   ├── class-drdbg-redaction.php
│   ├── class-drdbg-settings.php
│   └── class-drdbg-source.php
├── mu-plugin/
│   └── drdbg-loader.php      # Early capture loader (auto-installed)
└── docs/
    └── dr-debug-log-viewer-spec-v2.md
```

---

## FAQ

**Does this slow down my site?**  
No. Capture runs in memory and flushes to the database at shutdown. The impact on page load is minimal.

**What happens when I deactivate the plugin?**  
The MU-plugin loader is removed and capture stops. Your error data remains in the database.

**What happens when I delete the plugin?**  
All database tables, options, cron jobs, and the MU-plugin loader are completely removed.

**Is my data secure?**  
Sensitive values are redacted automatically. IP addresses are hashed, not stored in plain text.

**Does it work on multisite?**  
Yes. The plugin supports network activation.

---

## Contributing

Contributions are welcome! If you find a bug or have a feature idea:

1. [Open an issue](https://github.com/dr-debug/dr-debug/issues) describing the problem or proposal.
2. Fork the repository, create a branch, and submit a pull request.

Please keep changes focused and follow existing code style (WordPress Coding Standards, `drdbg_` prefix for functions, `Drdbg_` for classes).

---

## Changelog

### 1.0.0

- Initial release
- Fingerprint-based error grouping
- Secret redaction for sensitive data
- Automatic source detection (plugin / theme / core)
- Ring buffer for occurrences
- Admin dashboard with Persian RTL UI
- Admin bar indicator with new error count
- WP-Cron scheduled cleanup and log rotation
- MU-plugin loader for early error capture
- Demo data seeder
- AJAX and REST-powered admin interface

---

## License

This project is licensed under the **GNU General Public License v2.0 or later**.

See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

## فارسی

**دکتر دیباگ** یک افزونه وردپرس برای مدیریت و نمایش هوشمند خطاهای PHP است.

به‌جای باز کردن فایل خام `debug.log`، خطاها به‌صورت خودکار ثبت، بر اساس اثر انگشت گروه‌بندی، و در یک داشبورد مدرن فارسی (RTL) نمایش داده می‌شوند. اطلاعات حساس مثل رمز عبور و API key قبل از ذخیره‌سازی حذف می‌شوند و منبع خطا (افزونه، قالب، یا هسته) به‌صورت خودکار شناسایی می‌شود.

### نصب سریع

1. پوشه `dr-debug` را در `wp-content/plugins/` قرار دهید.
2. افزونه را از منوی **افزونه‌ها** فعال کنید.
3. از منوی **دکتر دیباگ** وارد داشبورد شوید.
4. برای آشنایی با رابط کاربری، **داده نمونه** را بزنید.

### غیرفعال‌سازی موقت

```php
define( 'DRDBG_DISABLE', true );
```

این خط را در `wp-config.php` قرار دهید تا ثبت خطا بدون غیرفعال کردن افزونه متوقف شود.

---

<p align="center">
  Made with care for WordPress developers · <a href="https://github.com/dr-debug/dr-debug">GitHub</a>
</p>
