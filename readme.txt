=== Dr. Debug — دکتر دیباگ ===
Contributors: drdebug
Tags: debug, error-log, log-viewer, developer-tools, persian
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smart error log viewer for WordPress with fingerprint-based grouping, secret redaction, and a modern Persian RTL dashboard.

== Description ==

**Dr. Debug** (دکتر دیباگ) is a smart error log viewer for WordPress that captures, groups, and displays PHP errors in a beautiful modern dashboard with full Persian (فارسی) RTL support.

= Key Features =

* **Fingerprint-based grouping** — Identical errors are automatically grouped using normalized fingerprints, so you see unique error patterns instead of duplicate noise.
* **Secret redaction** — Passwords, API keys, tokens, and other sensitive values are automatically stripped from error messages and stack traces.
* **Source detection** — Automatically identifies whether errors originate from plugins, themes, or WordPress core.
* **Ring buffer** — Keeps a configurable number of recent occurrences per error group, preventing database bloat.
* **Severity levels** — Errors classified as Fatal, Error, Warning, Notice, or Info based on PHP error type.
* **Status tracking** — Mark errors as New, Seen, Resolved, or Muted.
* **Admin bar indicator** — See the count of new errors right from the WordPress admin bar.
* **Smart cleanup** — Time-based, size-based, and status-based cleanup with WP-Cron scheduling.
* **Debug log rotation** — Automatic compression and rotation of `debug.log` when it exceeds 10 MB.
* **MU-plugin loader** — Captures errors that occur before regular plugins load, including fatal errors during plugin initialization.
* **Demo data seeder** — Generate realistic sample errors to preview the dashboard.

= English Description =

Dr. Debug is a comprehensive error monitoring solution for WordPress. It replaces the traditional `debug.log` file viewing experience with a structured, searchable, and filterable dashboard. The plugin captures PHP errors, warnings, and notices automatically, groups them by fingerprint to eliminate duplicates, and presents them in an intuitive admin interface.

= توضیحات فارسی =

دکتر دیباگ یک نمایشگر هوشمند خطاها برای وردپرس است. این افزونه خطاهای PHP را به‌صورت خودکار ثبت، دسته‌بندی و در یک داشبورد مدرن فارسی نمایش می‌دهد. با قابلیت گروه‌بندی هوشمند بر اساس اثر انگشت، مخفی‌سازی اطلاعات حساس، و شناسایی خودکار منبع خطا، مدیریت خطاها در وردپرس هرگز آسان‌تر نبوده است.

== Installation ==

1. Download or build `dr-debug.zip` (see `package.sh` in the repo) so the archive contains a single `dr-debug` folder with `dr-debug.php` inside it.
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip file, and install.
3. Alternatively, upload the `dr-debug` folder to `/wp-content/plugins/`.
4. Activate the plugin through the **Plugins** menu in WordPress.
5. The plugin will automatically create its database tables and copy the MU-plugin loader.
6. Navigate to **دکتر دیباگ** in the WordPress admin sidebar to view the dashboard.
7. Optionally click "داده نمونه" (Demo Data) to populate the dashboard with sample errors.

= Automatic Installation =

1. Go to Plugins → Add New in your WordPress admin.
2. Search for "Dr. Debug".
3. Click "Install Now" and then "Activate".

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. Error capture happens in-memory and is flushed to the database at shutdown. The impact on page load time is minimal.

= What happens when I deactivate the plugin? =

The MU-plugin loader is removed, so error capture stops immediately. Your error data is preserved in the database. WP-Cron cleanup jobs are unscheduled.

= What happens when I delete the plugin? =

All database tables, options, cron jobs, and the MU-plugin loader are completely removed. There is no leftover data.

= Is my data secure? =

Yes. The plugin automatically redacts passwords, API keys, Bearer tokens, and other sensitive values from error messages and stack traces. Client IP addresses are stored only as SHA-256 hashes.

= Can I use this on a multisite installation? =

Yes. The plugin is network-aware and can be activated on individual sites or network-activated.

= Does this work with PHP 8.x? =

Yes. Dr. Debug is compatible with PHP 7.4 through 8.3+.

== Changelog ==

= 1.0.0 =
* Initial release.
* Fingerprint-based error grouping.
* Secret redaction for sensitive data.
* Automatic source detection (plugin/theme/core).
* Ring buffer for occurrences.
* Admin dashboard with Persian RTL UI.
* Admin bar indicator with new error count.
* WP-Cron scheduled cleanup and log rotation.
* MU-plugin loader for early error capture.
* Demo data seeder for testing.
* Full AJAX-powered admin interface.

== Upgrade Notice ==

= 1.0.0 =
Initial release of Dr. Debug — دکتر دیباگ.
