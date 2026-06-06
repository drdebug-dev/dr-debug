/**
 * Dr. Debug — Admin JavaScript
 *
 * Single-page app pattern powering the Dr. Debug admin UI.
 * Uses vanilla jQuery (bundled with WordPress) for DOM manipulation
 * and AJAX calls. All user-facing text is in Persian (فارسی).
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

/* global drdbg, jQuery */

(function ($) {
	'use strict';

	// ─── State ──────────────────────────────────────────────────────
	var drdbgState = {
		currentView: 'dashboard',
		selectedErrorId: null,
		filters: {
			severity: '',
			status: '',
			sourceType: '',
			search: '',
			sortBy: 'last_seen',
			sortOrder: 'desc',
			page: 1
		},
		selectedIds: [],
		occurrencePage: 1,
		listErrors: [],
		errorDetailData: null
	};

	// ─── Severity / Status / Source type maps ───────────────────────
	var severityLabels = {
		1: 'اطلاعات',
		2: 'اعلان',
		3: 'هشدار',
		4: 'خطا',
		5: 'بحرانی'
	};

	var severityColors = {
		1: 'info',
		2: 'notice',
		3: 'warning',
		4: 'error',
		5: 'fatal'
	};

	var statusLabels = {
		0: 'جدید',
		1: 'دیده‌شده',
		2: 'رفع‌شده',
		3: 'بی‌صدا'
	};

	var statusColors = {
		0: 'new',
		1: 'seen',
		2: 'resolved',
		3: 'muted'
	};

	var sourceTypeLabels = {
		0: 'نامشخص',
		1: 'افزونه',
		2: 'پوسته',
		3: 'هسته'
	};

	var sourceTypeColors = {
		0: 'unknown',
		1: 'plugin',
		2: 'theme',
		3: 'core'
	};

	var contextTypeLabels = {
		0: 'وب',
		1: 'AJAX',
		2: 'REST',
		3: 'Cron',
		4: 'CLI'
	};

	// ─── Initialize ─────────────────────────────────────────────────
	$(document).ready(function () {
		drdbgInit();
	});

	function drdbgInit() {
		// Determine initial view from page slug.
		var page = (typeof drdbg !== 'undefined' && drdbg.page) ? drdbg.page : 'dr-debug';
		if (page === 'dr-debug-errors') {
			drdbgState.currentView = 'errors';
		} else if (page === 'dr-debug-settings') {
			drdbgState.currentView = 'settings';
		} else {
			drdbgState.currentView = 'dashboard';
		}

		drdbgRenderApp();
		drdbgLoadView(drdbgState.currentView);
	}

	// ─── Render App Shell ───────────────────────────────────────────
	function drdbgRenderApp() {
		var html = '';

		// Header.
		html += '<div class="drdbg-header">';
		html += '  <div class="drdbg-header-brand">';
		html += '    <span class="drdbg-header-brand-icon">🐛</span>';
		html += '    <h1 class="drdbg-header-brand-title">دکتر دیباگ</h1>';
		html += '    <span class="drdbg-header-brand-version">1.0.0</span>';
		html += '  </div>';
		html += '  <div class="drdbg-header-actions">';
		html += '    <button class="drdbg-btn drdbg-btn--ghost drdbg-seed-btn" title="ایجاد داده نمونه">';
		html += '      <span class="dashicons dashicons-database"></span> داده نمونه';
		html += '    </button>';
		html += '  </div>';
		html += '</div>';

		// Navigation.
		html += '<div class="drdbg-nav">';
		html += '  <button class="drdbg-nav-tab' + (drdbgState.currentView === 'dashboard' ? ' active' : '') + '" data-view="dashboard">';
		html += '    <span class="dashicons dashicons-dashboard"></span> داشبورد';
		html += '  </button>';
		html += '  <button class="drdbg-nav-tab' + (drdbgState.currentView === 'errors' ? ' active' : '') + '" data-view="errors">';
		html += '    <span class="dashicons dashicons-warning"></span> خطاها';
		html += '  </button>';
		html += '  <button class="drdbg-nav-tab' + (drdbgState.currentView === 'settings' ? ' active' : '') + '" data-view="settings">';
		html += '    <span class="dashicons dashicons-admin-generic"></span> تنظیمات';
		html += '  </button>';
		html += '</div>';

		// Content.
		html += '<div class="drdbg-content" id="drdbg-content"></div>';

		// Footer.
		html += '<div class="drdbg-footer">';
		html += '  دکتر دیباگ — نمایشگر هوشمند خطاها &bull; نسخه ۱.۰.۰';
		html += '</div>';

		$('#drdbg-app').html(html);
		drdbgBindNavEvents();
	}

	// ─── Navigation ─────────────────────────────────────────────────
	function drdbgBindNavEvents() {
		// Tab clicks.
		$(document).off('click.drdbg-nav', '.drdbg-nav-tab').on('click.drdbg-nav', '.drdbg-nav-tab', function () {
			var view = $(this).data('view');
			drdbgLoadView(view);
		});

		// Seed demo data.
		$(document).off('click.drdbg-seed', '.drdbg-seed-btn').on('click.drdbg-seed', '.drdbg-seed-btn', function () {
			drdbgSeedDemo();
		});
	}

	function drdbgLoadView(view) {
		drdbgState.currentView = view;

		// Update active tab.
		$('.drdbg-nav-tab').removeClass('active');
		$('.drdbg-nav-tab[data-view="' + view + '"]').addClass('active');

		// Reset filters and selection on view change.
		if (view === 'errors') {
			drdbgState.filters.page = 1;
			drdbgState.selectedIds = [];
		}

		// Load view content.
		switch (view) {
			case 'dashboard':
				drdbgLoadDashboard();
				break;
			case 'errors':
				drdbgLoadErrors();
				break;
			case 'error-detail':
				drdbgLoadErrorDetail(drdbgState.selectedErrorId);
				break;
			case 'settings':
				drdbgLoadSettings();
				break;
		}
	}

	// ─── Dashboard View ─────────────────────────────────────────────
	function drdbgLoadDashboard() {
		var $content = $('#drdbg-content');
		$content.html(drdbgSkeletonDashboard());

		$.post(drdbg.ajax_url, {
			action: 'drdbg_get_stats',
			nonce: drdbg.nonce
		}, function (res) {
			if (res.success) {
				drdbgRenderDashboard(res.data);
			} else {
				$content.html(drdbgEmptyState('خطا', 'دریافت آمار با مشکل مواجه شد.', '🐛'));
			}
		}).fail(function () {
			$content.html(drdbgEmptyState('خطا', 'اتصال به سرور برقرار نشد.', '🔌'));
		});
	}

	function drdbgRenderDashboard(stats) {
		var total = stats.total || 0;
		var bySeverity = stats.by_severity || {};
		var bySourceType = stats.by_source_type || {};
		var byStatus = stats.by_status || {};
		var recentTrend = stats.recent_trend || {};
		var topSources = stats.top_sources || [];

		var newCount = byStatus[0] || 0;
		var fatalCount = bySeverity[5] || 0;
		var resolvedCount = byStatus[2] || 0;

		var html = '';

		// Stat cards.
		html += '<div class="drdbg-stats-grid">';
		html += '  <div class="drdbg-stat-card drdbg-stat-card--total">';
		html += '    <div class="drdbg-stat-card-label">کل خطاها</div>';
		html += '    <div class="drdbg-stat-card-value">' + drdbgToPersianDigits(total) + '</div>';
		html += '    <div class="drdbg-stat-card-sub">گروه خطای ثبت‌شده</div>';
		html += '  </div>';
		html += '  <div class="drdbg-stat-card drdbg-stat-card--fatal">';
		html += '    <div class="drdbg-stat-card-label">بحرانی</div>';
		html += '    <div class="drdbg-stat-card-value">' + drdbgToPersianDigits(fatalCount) + '</div>';
		html += '    <div class="drdbg-stat-card-sub">خطاهای بحرانی</div>';
		html += '  </div>';
		html += '  <div class="drdbg-stat-card drdbg-stat-card--new">';
		html += '    <div class="drdbg-stat-card-label">جدید</div>';
		html += '    <div class="drdbg-stat-card-value">' + drdbgToPersianDigits(newCount) + '</div>';
		html += '    <div class="drdbg-stat-card-sub">خطاهای دیده‌نشده</div>';
		html += '  </div>';
		html += '  <div class="drdbg-stat-card drdbg-stat-card--resolved">';
		html += '    <div class="drdbg-stat-card-label">رفع‌شده</div>';
		html += '    <div class="drdbg-stat-card-value">' + drdbgToPersianDigits(resolvedCount) + '</div>';
		html += '    <div class="drdbg-stat-card-sub">خطاهای رفع‌شده</div>';
		html += '  </div>';
		html += '</div>';

		// Two-column section.
		html += '<div class="drdbg-two-col">';

		// Severity distribution.
		html += '  <div class="drdbg-chart-section">';
		html += '    <h3 class="drdbg-chart-section-title">توزیع شدت</h3>';
		html += '    <div class="drdbg-hbar-chart">';
		var maxSeverityVal = Math.max.apply(null, [bySeverity[5] || 0, bySeverity[4] || 0, bySeverity[3] || 0, bySeverity[2] || 0, bySeverity[1] || 0, 1]);
		var severityOrder = [5, 4, 3, 2, 1];
		var severityBarColors = {
			5: 'var(--drdbg-severity-fatal)',
			4: 'var(--drdbg-severity-error)',
			3: 'var(--drdbg-severity-warning)',
			2: 'var(--drdbg-severity-notice)',
			1: 'var(--drdbg-severity-info)'
		};
		for (var si = 0; si < severityOrder.length; si++) {
			var sev = severityOrder[si];
			var sevCount = bySeverity[sev] || 0;
			var sevPct = maxSeverityVal > 0 ? Math.round((sevCount / maxSeverityVal) * 100) : 0;
			html += '      <div class="drdbg-hbar-row">';
			html += '        <span class="drdbg-hbar-label">' + severityLabels[sev] + '</span>';
			html += '        <div class="drdbg-hbar-track">';
			html += '          <div class="drdbg-hbar-fill" style="width:' + sevPct + '%;background:' + severityBarColors[sev] + ';"></div>';
			html += '        </div>';
			html += '        <span class="drdbg-hbar-count">' + drdbgToPersianDigits(sevCount) + '</span>';
			html += '      </div>';
		}
		html += '    </div>';
		html += '  </div>';

		// Recent trend.
		html += '  <div class="drdbg-chart-section">';
		html += '    <h3 class="drdbg-chart-section-title">روند هفتگی</h3>';
		html += '    <div class="drdbg-bar-chart">';
		var trendDays = Object.keys(recentTrend).sort();
		var maxTrend = 0;
		for (var ti = 0; ti < trendDays.length; ti++) {
			if (recentTrend[trendDays[ti]] > maxTrend) maxTrend = recentTrend[trendDays[ti]];
		}
		if (maxTrend === 0) maxTrend = 1;

		// Fill in last 7 days even if some have 0.
		var dayLabels = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
		for (var di = 6; di >= 0; di--) {
			var d = new Date();
			d.setDate(d.getDate() - di);
			var dKey = d.toISOString().slice(0, 10);
			var dVal = recentTrend[dKey] || 0;
			var dLabel = dayLabels[d.getDay()];
			var dPct = Math.round((dVal / maxTrend) * 100);
			html += '  <div class="drdbg-bar-chart-col">';
			html += '    <span class="drdbg-bar-chart-value">' + drdbgToPersianDigits(dVal) + '</span>';
			html += '    <div class="drdbg-bar-chart-bar" style="height:' + Math.max(dPct, 3) + '%;background:var(--drdbg-primary);"></div>';
			html += '    <span class="drdbg-bar-chart-label">' + dLabel.slice(0, 3) + '</span>';
			html += '  </div>';
		}
		html += '    </div>';
		html += '  </div>';

		html += '</div>';

		// Top sources.
		if (topSources.length > 0) {
			html += '<div class="drdbg-chart-section">';
			html += '  <h3 class="drdbg-chart-section-title">منابع پرخطا</h3>';
			html += '  <table class="drdbg-top-sources">';
			html += '    <thead><tr><th>منبع</th><th>نوع</th><th>تعداد</th></tr></thead>';
			html += '    <tbody>';
			for (var tsi = 0; tsi < topSources.length; tsi++) {
				var src = topSources[tsi];
				html += '      <tr>';
				html += '        <td>' + drdbgEscapeHtml(src.slug || '—') + '</td>';
				html += '        <td>' + drdbgSourceTypeBadge(src.source_type) + '</td>';
				html += '        <td>' + drdbgToPersianDigits(src.count) + '</td>';
				html += '      </tr>';
			}
			html += '    </tbody>';
			html += '  </table>';
			html += '</div>';
		}

		$('#drdbg-content').html(html);
	}

	// ─── Errors List View ───────────────────────────────────────────
	function drdbgLoadErrors() {
		var $content = $('#drdbg-content');
		$content.html(drdbgSkeletonList());

		var ajaxData = {
			action: 'drdbg_get_errors',
			nonce: drdbg.nonce,
			severity: drdbgState.filters.severity || '',
			status: drdbgState.filters.status || '',
			source_type: drdbgState.filters.sourceType || '',
			search: drdbgState.filters.search || '',
			orderby: drdbgState.filters.sortBy || 'last_seen',
			order: drdbgState.filters.sortOrder || 'desc',
			page: drdbgState.filters.page || 1,
			per_page: 15
		};

		// Remove empty filters.
		Object.keys(ajaxData).forEach(function (k) {
			if (ajaxData[k] === '' || ajaxData[k] === null || ajaxData[k] === undefined) {
				delete ajaxData[k];
			}
		});

		$.get(drdbg.ajax_url, ajaxData, function (res) {
			if (res.success) {
				drdbgRenderErrors(res.data);
			} else {
				$content.html(drdbgEmptyState('خطا', 'دریافت لیست خطاها با مشکل مواجه شد.', '⚠️'));
			}
		}).fail(function () {
			$content.html(drdbgEmptyState('خطا', 'اتصال به سرور برقرار نشد.', '🔌'));
		});
	}

	function drdbgRenderErrors(data) {
		var errors = data.errors || [];
		drdbgState.listErrors = errors;
		var total = data.total || 0;
		var pages = data.pages || 1;
		var currentPage = drdbgState.filters.page || 1;

		var html = '';

		// Filter bar.
		html += '<div class="drdbg-filter-bar">';
		html += '  <select class="drdbg-filter-severity">';
		html += '    <option value="">همه شدت‌ها</option>';
		html += '    <option value="5"' + (drdbgState.filters.severity === '5' ? ' selected' : '') + '>بحرانی</option>';
		html += '    <option value="4"' + (drdbgState.filters.severity === '4' ? ' selected' : '') + '>خطا</option>';
		html += '    <option value="3"' + (drdbgState.filters.severity === '3' ? ' selected' : '') + '>هشدار</option>';
		html += '    <option value="2"' + (drdbgState.filters.severity === '2' ? ' selected' : '') + '>اعلان</option>';
		html += '    <option value="1"' + (drdbgState.filters.severity === '1' ? ' selected' : '') + '>اطلاعات</option>';
		html += '  </select>';
		html += '  <select class="drdbg-filter-status">';
		html += '    <option value="">همه وضعیت‌ها</option>';
		html += '    <option value="0"' + (drdbgState.filters.status === '0' ? ' selected' : '') + '>جدید</option>';
		html += '    <option value="1"' + (drdbgState.filters.status === '1' ? ' selected' : '') + '>دیده‌شده</option>';
		html += '    <option value="2"' + (drdbgState.filters.status === '2' ? ' selected' : '') + '>رفع‌شده</option>';
		html += '    <option value="3"' + (drdbgState.filters.status === '3' ? ' selected' : '') + '>بی‌صدا</option>';
		html += '  </select>';
		html += '  <select class="drdbg-filter-source">';
		html += '    <option value="">همه منابع</option>';
		html += '    <option value="1"' + (drdbgState.filters.sourceType === '1' ? ' selected' : '') + '>افزونه</option>';
		html += '    <option value="2"' + (drdbgState.filters.sourceType === '2' ? ' selected' : '') + '>پوسته</option>';
		html += '    <option value="3"' + (drdbgState.filters.sourceType === '3' ? ' selected' : '') + '>هسته</option>';
		html += '  </select>';
		html += '  <div class="drdbg-filter-search">';
		html += '    <span class="drdbg-filter-search-icon dashicons dashicons-search"></span>';
		html += '    <input type="text" placeholder="جستجو در پیام یا فایل..." value="' + drdbgEscapeHtml(drdbgState.filters.search) + '" class="drdbg-filter-search-input">';
		html += '  </div>';
		html += '</div>';

		// Batch actions bar.
		html += '<div class="drdbg-batch-bar" id="drdbg-batch-bar">';
		html += '  <span class="drdbg-batch-bar-count" id="drdbg-batch-count">۰</span> مورد انتخاب‌شده';
		html += '  <button class="drdbg-btn drdbg-btn--small drdbg-btn--success drdbg-batch-seen">دیده‌شده</button>';
		html += '  <button class="drdbg-btn drdbg-btn--small drdbg-btn--primary drdbg-batch-resolved">رفع‌شده</button>';
		html += '  <button class="drdbg-btn drdbg-btn--small drdbg-btn--ghost drdbg-batch-muted">بی‌صدا</button>';
		html += '  <button class="drdbg-btn drdbg-btn--small drdbg-btn--danger drdbg-batch-delete">حذف</button>';
		html += '</div>';

		if (errors.length === 0) {
			html += drdbgEmptyState('خطایی یافت نشد', 'هنوز خطایی ثبت نشده یا فیلترها نتیجه‌ای ندارند.', '🔍');
			$('#drdbg-content').html(html);
			drdbgBindFilterEvents();
			return;
		}

		// Error table.
		html += '<div class="drdbg-table-wrap">';
		html += '  <table class="drdbg-table">';
		html += '    <thead>';
		html += '      <tr>';
		html += '        <th class="drdbg-col-check"><input type="checkbox" id="drdbg-select-all"></th>';
		html += '        <th>شدت</th>';
		html += '        <th>پیام</th>';
		html += '        <th>منبع</th>';
		html += '        <th>فایل</th>';
		html += '        <th>وضعیت</th>';
		html += '        <th>تعداد</th>';
		html += '        <th>آخرین مشاهده</th>';
		html += '        <th class="drdbg-col-actions"></th>';
		html += '      </tr>';
		html += '    </thead>';
		html += '    <tbody>';

		for (var i = 0; i < errors.length; i++) {
			var e = errors[i];
			var isSelected = drdbgState.selectedIds.indexOf(parseInt(e.id, 10)) !== -1;
			html += '      <tr data-id="' + e.id + '"' + (isSelected ? ' class="selected"' : '') + '>';
			html += '        <td class="drdbg-col-check"><input type="checkbox" class="drdbg-row-check" data-id="' + e.id + '"' + (isSelected ? ' checked' : '') + '></td>';
			html += '        <td>' + drdbgSeverityBadge(e.severity_level) + '</td>';
			html += '        <td class="drdbg-col-message" title="' + drdbgEscapeAttr(e.message) + '">' + drdbgEscapeHtml(e.message) + '</td>';
			html += '        <td>' + drdbgSourceTypeBadge(e.source_type) + ' ' + drdbgEscapeHtml(e.source_slug || '—') + '</td>';
			html += '        <td class="drdbg-col-file" title="' + drdbgEscapeAttr(e.file) + '">' + drdbgEscapeHtml(e.file) + '</td>';
			html += '        <td>' + drdbgStatusBadge(e.status) + '</td>';
			html += '        <td class="drdbg-col-count">' + drdbgToPersianDigits(e.count) + '</td>';
			html += '        <td class="drdbg-col-time">' + drdbgRelativeTime(e.last_seen) + '</td>';
			html += '        <td class="drdbg-col-actions">';
			html += '          <button type="button" class="drdbg-btn drdbg-btn--ghost drdbg-btn--icon drdbg-copy-error-summary" data-index="' + i + '" title="' + drdbgEscapeAttr(drdbg.strings.copy_summary) + '">';
			html += '            <span class="dashicons dashicons-admin-page"></span>';
			html += '          </button>';
			html += '        </td>';
			html += '      </tr>';
		}

		html += '    </tbody>';
		html += '  </table>';
		html += '</div>';

		// Pagination.
		html += '<div class="drdbg-pagination">';
		html += '  <div class="drdbg-pagination-info">';
		html += '    ' + drdbgToPersianDigits(total) + ' خطا &bull; صفحه ' + drdbgToPersianDigits(currentPage) + ' از ' + drdbgToPersianDigits(pages);
		html += '  </div>';
		html += '  <div class="drdbg-pagination-pages">';
		html += '    <button class="drdbg-pagination-btn drdbg-page-prev"' + (currentPage <= 1 ? ' disabled' : '') + '>قبلی</button>';

		var startPage = Math.max(1, currentPage - 2);
		var endPage = Math.min(pages, currentPage + 2);
		for (var p = startPage; p <= endPage; p++) {
			html += '    <button class="drdbg-pagination-btn' + (p === currentPage ? ' active' : '') + ' drdbg-page-btn" data-page="' + p + '">' + drdbgToPersianDigits(p) + '</button>';
		}

		html += '    <button class="drdbg-pagination-btn drdbg-page-next"' + (currentPage >= pages ? ' disabled' : '') + '>بعدی</button>';
		html += '  </div>';
		html += '</div>';

		$('#drdbg-content').html(html);
		drdbgBindFilterEvents();
		drdbgBindTableEvents();
	}

	function drdbgBindFilterEvents() {
		// Severity filter.
		$('.drdbg-filter-severity').off('change.drdbg').on('change.drdbg', function () {
			drdbgState.filters.severity = $(this).val();
			drdbgState.filters.page = 1;
			drdbgLoadErrors();
		});

		// Status filter.
		$('.drdbg-filter-status').off('change.drdbg').on('change.drdbg', function () {
			drdbgState.filters.status = $(this).val();
			drdbgState.filters.page = 1;
			drdbgLoadErrors();
		});

		// Source type filter.
		$('.drdbg-filter-source').off('change.drdbg').on('change.drdbg', function () {
			drdbgState.filters.sourceType = $(this).val();
			drdbgState.filters.page = 1;
			drdbgLoadErrors();
		});

		// Search.
		var searchTimer = null;
		$('.drdbg-filter-search-input').off('input.drdbg').on('input.drdbg', function () {
			var val = $(this).val();
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				drdbgState.filters.search = val;
				drdbgState.filters.page = 1;
				drdbgLoadErrors();
			}, 400);
		});
	}

	function drdbgBindTableEvents() {
		// Row click → detail.
		$(document).off('click.drdbg-row', '.drdbg-table tbody tr').on('click.drdbg-row', '.drdbg-table tbody tr', function (e) {
			// Don't trigger on checkbox or copy button click.
			if ($(e.target).closest('.drdbg-copy-btn, .drdbg-copy-error-summary').length) return;
			if ($(e.target).is('input[type="checkbox"]') || $(e.target).is('select')) return;
			var id = $(this).data('id');
			drdbgState.selectedErrorId = id;
			drdbgState.occurrencePage = 1;
			drdbgLoadView('error-detail');
		});

		// Select-all checkbox.
		$('#drdbg-select-all').off('change.drdbg').on('change.drdbg', function () {
			var checked = $(this).is(':checked');
			$('.drdbg-row-check').prop('checked', checked);
			if (checked) {
				$('.drdbg-row-check').each(function () {
					var id = parseInt($(this).data('id'), 10);
					if (drdbgState.selectedIds.indexOf(id) === -1) {
						drdbgState.selectedIds.push(id);
					}
				});
			} else {
				drdbgState.selectedIds = [];
			}
			drdbgUpdateBatchBar();
		});

		// Individual checkboxes.
		$(document).off('change.drdbg-check', '.drdbg-row-check').on('change.drdbg-check', '.drdbg-row-check', function () {
			var id = parseInt($(this).data('id'), 10);
			if ($(this).is(':checked')) {
				if (drdbgState.selectedIds.indexOf(id) === -1) {
					drdbgState.selectedIds.push(id);
				}
			} else {
				drdbgState.selectedIds = drdbgState.selectedIds.filter(function (v) { return v !== id; });
			}
			drdbgUpdateBatchBar();
		});

		// Batch actions.
		$('.drdbg-batch-seen').off('click.drdbg').on('click.drdbg', function () {
			drdbgBatchUpdate(drdbgState.selectedIds, 1);
		});
		$('.drdbg-batch-resolved').off('click.drdbg').on('click.drdbg', function () {
			drdbgBatchUpdate(drdbgState.selectedIds, 2);
		});
		$('.drdbg-batch-muted').off('click.drdbg').on('click.drdbg', function () {
			drdbgBatchUpdate(drdbgState.selectedIds, 3);
		});
		$('.drdbg-batch-delete').off('click.drdbg').on('click.drdbg', function () {
			if (confirm(drdbg.strings.confirm_delete)) {
				drdbgDeleteErrors(drdbgState.selectedIds);
			}
		});

		// Pagination.
		$('.drdbg-page-btn').off('click.drdbg').on('click.drdbg', function () {
			drdbgState.filters.page = parseInt($(this).data('page'), 10);
			drdbgLoadErrors();
		});
		$('.drdbg-page-prev').off('click.drdbg').on('click.drdbg', function () {
			if (drdbgState.filters.page > 1) {
				drdbgState.filters.page--;
				drdbgLoadErrors();
			}
		});
		$('.drdbg-page-next').off('click.drdbg').on('click.drdbg', function () {
			drdbgState.filters.page++;
			drdbgLoadErrors();
		});

		// Copy error summary from list.
		$(document).off('click.drdbg-copy-summary', '.drdbg-copy-error-summary').on('click.drdbg-copy-summary', '.drdbg-copy-error-summary', function (e) {
			e.stopPropagation();
			var idx = parseInt($(this).data('index'), 10);
			var err = drdbgState.listErrors[idx];
			if (err) {
				drdbgCopyToClipboard(drdbgFormatErrorSummary(err));
			}
		});
	}

	function drdbgUpdateBatchBar() {
		var count = drdbgState.selectedIds.length;
		$('#drdbg-batch-count').text(drdbgToPersianDigits(count));
		if (count > 0) {
			$('#drdbg-batch-bar').addClass('visible');
		} else {
			$('#drdbg-batch-bar').removeClass('visible');
		}
	}

	// ─── Error Detail View ──────────────────────────────────────────
	function drdbgLoadErrorDetail(id) {
		var $content = $('#drdbg-content');
		$content.html(drdbgSkeletonDetail());

		$.get(drdbg.ajax_url, {
			action: 'drdbg_get_error',
			nonce: drdbg.nonce,
			id: id,
			page: drdbgState.occurrencePage
		}, function (res) {
			if (res.success && res.data && res.data.error) {
				drdbgRenderErrorDetail(res.data);
			} else {
				$content.html(drdbgEmptyState('یافت نشد', 'خطای درخواست‌شده پیدا نشد.', '🔍'));
			}
		}).fail(function () {
			$content.html(drdbgEmptyState('خطا', 'اتصال به سرور برقرار نشد.', '🔌'));
		});
	}

	function drdbgRenderErrorDetail(data) {
		drdbgState.errorDetailData = data;
		var error = data.error;
		var occurrences = data.occurrences || [];
		var totalOccurrences = data.total_occurrences || 0;
		var occurrencePages = data.occurrence_pages || 1;

		var html = '';

		// Back link.
		html += '<button class="drdbg-back-link" id="drdbg-back-to-errors">';
		html += '  <span class="dashicons dashicons-arrow-right-alt"></span> بازگشت به لیست خطاها';
		html += '</button>';

		// Header with badges.
		html += '<div class="drdbg-detail">';
		html += '  <div class="drdbg-detail-section">';
		html += '    <div class="drdbg-detail-header">';
		html += '      <h2 class="drdbg-detail-title">' + drdbgEscapeHtml(error.message) + '</h2>';
		html += '      <div class="drdbg-detail-header-end">';
		html += '        <div class="drdbg-detail-meta">';
		html += drdbgSeverityBadge(error.severity_level);
		html += drdbgStatusBadge(error.status);
		html += drdbgSourceTypeBadge(error.source_type);
		html += '        </div>';
		html += '        <div class="drdbg-detail-copy-actions">';
		html += drdbgCopyButton('drdbg-copy-error-summary-detail', drdbg.strings.copy_summary);
		html += drdbgCopyButton('drdbg-copy-error-full', drdbg.strings.copy_all);
		html += '        </div>';
		html += '      </div>';
		html += '    </div>';
		html += '  </div>';

		// Info grid.
		html += '  <div class="drdbg-detail-section">';
		html += '    <h3 class="drdbg-detail-section-title">اطلاعات خطا</h3>';
		html += '    <div class="drdbg-info-grid">';
		html += '      <div class="drdbg-info-item">';
		html += '        <span class="drdbg-info-item-label">فایل</span>';
		html += '        <span class="drdbg-info-item-value" dir="ltr" style="text-align:left;">' + drdbgEscapeHtml(error.file) + '</span>';
		html += '      </div>';
		html += '      <div class="drdbg-info-item">';
		html += '        <span class="drdbg-info-item-label">خط</span>';
		html += '        <span class="drdbg-info-item-value">' + drdbgToPersianDigits(error.line) + '</span>';
		html += '      </div>';
		html += '      <div class="drdbg-info-item">';
		html += '        <span class="drdbg-info-item-label">اولین مشاهده</span>';
		html += '        <span class="drdbg-info-item-value">' + drdbgRelativeTime(error.first_seen) + '</span>';
		html += '      </div>';
		html += '      <div class="drdbg-info-item">';
		html += '        <span class="drdbg-info-item-label">آخرین مشاهده</span>';
		html += '        <span class="drdbg-info-item-value">' + drdbgRelativeTime(error.last_seen) + '</span>';
		html += '      </div>';
		html += '      <div class="drdbg-info-item">';
		html += '        <span class="drdbg-info-item-label">تعداد تکرار</span>';
		html += '        <span class="drdbg-info-item-value">' + drdbgToPersianDigits(error.count) + '</span>';
		html += '      </div>';
		html += '      <div class="drdbg-info-item">';
		html += '        <span class="drdbg-info-item-label">نوع خطا</span>';
		html += '        <span class="drdbg-info-item-value" dir="ltr">' + drdbgErrorTypeLabel(error.error_type) + '</span>';
		html += '      </div>';
		html += '      <div class="drdbg-info-item">';
		html += '        <span class="drdbg-info-item-label">منبع</span>';
		html += '        <span class="drdbg-info-item-value">' + drdbgEscapeHtml(error.source_slug || '—') + '</span>';
		html += '      </div>';
		html += '      <div class="drdbg-info-item">';
		html += '        <span class="drdbg-info-item-label">وضعیت</span>';
		html += '        <span class="drdbg-info-item-value">';
		html += '          <select class="drdbg-status-select" data-id="' + error.id + '">';
		html += '            <option value="0"' + (parseInt(error.status, 10) === 0 ? ' selected' : '') + '>جدید</option>';
		html += '            <option value="1"' + (parseInt(error.status, 10) === 1 ? ' selected' : '') + '>دیده‌شده</option>';
		html += '            <option value="2"' + (parseInt(error.status, 10) === 2 ? ' selected' : '') + '>رفع‌شده</option>';
		html += '            <option value="3"' + (parseInt(error.status, 10) === 3 ? ' selected' : '') + '>بی‌صدا</option>';
		html += '          </select>';
		html += '        </span>';
		html += '      </div>';
		html += '    </div>';

		// Actions.
		html += '    <div style="margin-top:16px;display:flex;gap:8px;">';
		html += '      <button class="drdbg-btn drdbg-btn--danger drdbg-delete-error" data-id="' + error.id + '">حذف خطا</button>';
		html += '    </div>';
		html += '  </div>';

		// Occurrences.
		html += '  <div class="drdbg-detail-section">';
		html += '    <h3 class="drdbg-detail-section-title">وقوع‌ها (' + drdbgToPersianDigits(totalOccurrences) + ')</h3>';

		if (occurrences.length === 0) {
			html += '    <p style="color:var(--drdbg-text-muted);">وقوعی ثبت نشده.</p>';
		} else {
			for (var oi = 0; oi < occurrences.length; oi++) {
				var occ = occurrences[oi];
				html += '    <div class="drdbg-occurrence">';
				html += '      <div class="drdbg-occurrence-header">';
				html += '        <span class="drdbg-occurrence-time">' + drdbgRelativeTime(occ.occurred_at) + '</span>';
				html += '        <div class="drdbg-occurrence-header-end">';
				html += '          <span class="drdbg-badge drdbg-badge--' + (sourceTypeColors[occ.context_type] || 'unknown') + '">' + (contextTypeLabels[occ.context_type] || '—') + '</span>';
				html += drdbgCopyButton('drdbg-copy-occurrence', drdbg.strings.copy_occurrence, oi);
				html += '        </div>';
				html += '      </div>';
				html += '      <div class="drdbg-occurrence-meta">';
				if (occ.request_url) {
					html += '        <span class="drdbg-occurrence-meta-item" dir="ltr">📍 ' + drdbgEscapeHtml(occ.request_url) + '</span>';
				}
				if (occ.request_method) {
					html += '        <span class="drdbg-occurrence-meta-item">' + drdbgEscapeHtml(occ.request_method) + '</span>';
				}
				if (occ.memory_peak) {
					html += '        <span class="drdbg-occurrence-meta-item">💾 ' + drdbgFormatMemory(occ.memory_peak) + '</span>';
				}
				if (occ.php_version) {
					html += '        <span class="drdbg-occurrence-meta-item" dir="ltr">PHP ' + drdbgEscapeHtml(occ.php_version) + '</span>';
				}
				if (occ.wp_version) {
					html += '        <span class="drdbg-occurrence-meta-item" dir="ltr">WP ' + drdbgEscapeHtml(occ.wp_version) + '</span>';
				}
				html += '      </div>';

				// Stack trace (collapsible).
				if (occ.stack_trace) {
					html += '      <div style="margin-top:8px;">';
					html += '        <button class="drdbg-stack-toggle"><span class="dashicons dashicons-arrow-down"></span> نمایش ردیابی پشته</button>';
					html += '        <pre class="drdbg-stack-trace">' + drdbgEscapeHtml(occ.stack_trace) + '</pre>';
					html += '      </div>';
				}
				html += '    </div>';
			}
		}

		// Occurrence pagination.
		if (occurrencePages > 1) {
			html += '    <div class="drdbg-pagination" style="border-radius:0 0 var(--drdbg-radius-lg) var(--drdbg-radius-lg);">';
			html += '      <div class="drdbg-pagination-info">صفحه ' + drdbgToPersianDigits(drdbgState.occurrencePage) + ' از ' + drdbgToPersianDigits(occurrencePages) + '</div>';
			html += '      <div class="drdbg-pagination-pages">';
			html += '        <button class="drdbg-pagination-btn drdbg-occ-prev"' + (drdbgState.occurrencePage <= 1 ? ' disabled' : '') + '>قبلی</button>';
			html += '        <button class="drdbg-pagination-btn drdbg-occ-next"' + (drdbgState.occurrencePage >= occurrencePages ? ' disabled' : '') + '>بعدی</button>';
			html += '      </div>';
			html += '    </div>';
		}

		html += '  </div>';
		html += '</div>';

		$('#drdbg-content').html(html);
		drdbgBindDetailEvents();
	}

	function drdbgBindDetailEvents() {
		// Back to errors.
		$(document).off('click.drdbg-back', '#drdbg-back-to-errors').on('click.drdbg-back', '#drdbg-back-to-errors', function () {
			drdbgLoadView('errors');
		});

		// Status change.
		$(document).off('change.drdbg-status', '.drdbg-status-select').on('change.drdbg-status', '.drdbg-status-select', function () {
			var id = $(this).data('id');
			var status = $(this).val();
			drdbgUpdateStatus(id, status);
		});

		// Delete error.
		$(document).off('click.drdbg-del', '.drdbg-delete-error').on('click.drdbg-del', '.drdbg-delete-error', function () {
			if (confirm(drdbg.strings.confirm_delete)) {
				var id = $(this).data('id');
				drdbgDeleteErrors([id]);
			}
		});

		// Stack trace toggle.
		$(document).off('click.drdbg-stack', '.drdbg-stack-toggle').on('click.drdbg-stack', '.drdbg-stack-toggle', function () {
			$(this).toggleClass('expanded');
			$(this).next('.drdbg-stack-trace').toggleClass('visible');
		});

		// Occurrence pagination.
		$(document).off('click.drdbg-occ-prev', '.drdbg-occ-prev').on('click.drdbg-occ-prev', '.drdbg-occ-prev', function () {
			if (drdbgState.occurrencePage > 1) {
				drdbgState.occurrencePage--;
				drdbgLoadErrorDetail(drdbgState.selectedErrorId);
			}
		});
		$(document).off('click.drdbg-occ-next', '.drdbg-occ-next').on('click.drdbg-occ-next', '.drdbg-occ-next', function () {
			drdbgState.occurrencePage++;
			drdbgLoadErrorDetail(drdbgState.selectedErrorId);
		});

		// Copy error summary from detail.
		$(document).off('click.drdbg-copy-detail-summary', '.drdbg-copy-error-summary-detail').on('click.drdbg-copy-detail-summary', '.drdbg-copy-error-summary-detail', function (e) {
			e.preventDefault();
			if (drdbgState.errorDetailData && drdbgState.errorDetailData.error) {
				drdbgCopyToClipboard(drdbgFormatErrorSummary(drdbgState.errorDetailData.error));
			}
		});

		// Copy full error report (error + visible occurrences).
		$(document).off('click.drdbg-copy-full', '.drdbg-copy-error-full').on('click.drdbg-copy-full', '.drdbg-copy-error-full', function (e) {
			e.preventDefault();
			if (drdbgState.errorDetailData) {
				drdbgCopyToClipboard(drdbgFormatErrorFull(drdbgState.errorDetailData));
			}
		});

		// Copy single occurrence.
		$(document).off('click.drdbg-copy-occ', '.drdbg-copy-occurrence').on('click.drdbg-copy-occ', '.drdbg-copy-occurrence', function (e) {
			e.preventDefault();
			var idx = parseInt($(this).data('occ-index'), 10);
			var detail = drdbgState.errorDetailData;
			if (detail && detail.error && detail.occurrences && detail.occurrences[idx]) {
				drdbgCopyToClipboard(drdbgFormatOccurrence(detail.error, detail.occurrences[idx], idx + 1));
			}
		});
	}

	// ─── Settings View ──────────────────────────────────────────────
	function drdbgLoadSettings() {
		var $content = $('#drdbg-content');
		$content.html(drdbgSkeletonSettings());

		$.post(drdbg.ajax_url, {
			action: 'drdbg_get_settings',
			nonce: drdbg.nonce
		}, function (res) {
			if (res.success) {
				drdbgRenderSettings(res.data);
			} else {
				$content.html(drdbgEmptyState('خطا', 'دریافت تنظیمات با مشکل مواجه شد.', '⚠️'));
			}
		}).fail(function () {
			$content.html(drdbgEmptyState('خطا', 'اتصال به سرور برقرار نشد.', '🔌'));
		});
	}

	function drdbgRenderSettings(settings) {
		var html = '';

		html += '<div class="drdbg-settings-form">';

		// General settings.
		html += '  <div class="drdbg-settings-section">';
		html += '    <h3 class="drdbg-settings-section-title">تنظیمات عمومی</h3>';

		// Cleanup days.
		html += '    <div class="drdbg-field">';
		html += '      <div>';
		html += '        <div class="drdbg-field-label">روزهای پاکسازی</div>';
		html += '        <div class="drdbg-field-desc">خطاهای قدیمی‌تر از این مدت حذف می‌شوند.</div>';
		html += '      </div>';
		html += '      <div class="drdbg-field-control">';
		html += '        <input type="number" id="drdbg-cleanup-days" value="' + drdbgEscapeAttr(settings.cleanup_days) + '" min="1" max="365">';
		html += '      </div>';
		html += '    </div>';

		// Max occurrences.
		html += '    <div class="drdbg-field">';
		html += '      <div>';
		html += '        <div class="drdbg-field-label">حداکثر وقوع</div>';
		html += '        <div class="drdbg-field-desc">حداکثر تعداد وقوع ذخیره‌شده برای هر خطا (حلقه حافظه).</div>';
		html += '      </div>';
		html += '      <div class="drdbg-field-control">';
		html += '        <input type="number" id="drdbg-max-occurrences" value="' + drdbgEscapeAttr(settings.max_occurrences) + '" min="1" max="100">';
		html += '      </div>';
		html += '    </div>';

		// Min severity.
		html += '    <div class="drdbg-field">';
		html += '      <div>';
		html += '        <div class="drdbg-field-label">حداقل شدت</div>';
		html += '        <div class="drdbg-field-desc">خطاهایی با شدت کمتر از این مقدار نادیده گرفته می‌شوند.</div>';
		html += '      </div>';
		html += '      <div class="drdbg-field-control">';
		html += '        <select id="drdbg-min-severity">';
		html += '          <option value="1"' + (parseInt(settings.min_severity, 10) === 1 ? ' selected' : '') + '>اطلاعات</option>';
		html += '          <option value="2"' + (parseInt(settings.min_severity, 10) === 2 ? ' selected' : '') + '>اعلان</option>';
		html += '          <option value="3"' + (parseInt(settings.min_severity, 10) === 3 ? ' selected' : '') + '>هشدار</option>';
		html += '          <option value="4"' + (parseInt(settings.min_severity, 10) === 4 ? ' selected' : '') + '>خطا</option>';
		html += '          <option value="5"' + (parseInt(settings.min_severity, 10) === 5 ? ' selected' : '') + '>بحرانی</option>';
		html += '        </select>';
		html += '      </div>';
		html += '    </div>';

		html += '  </div>';

		// Security settings.
		html += '  <div class="drdbg-settings-section">';
		html += '    <h3 class="drdbg-settings-section-title">تنظیمات امنیتی</h3>';

		// Redaction.
		html += '    <div class="drdbg-field">';
		html += '      <div>';
		html += '        <div class="drdbg-field-label">مخفی‌سازی اطلاعات حساس</div>';
		html += '        <div class="drdbg-field-desc">مقادیر حساس مثل رمز عبور و توکن‌ها از پیام‌ها و ردیابی‌ها حذف می‌شوند.</div>';
		html += '      </div>';
		html += '      <div class="drdbg-field-control">';
		html += '        <label class="drdbg-toggle">';
		html += '          <input type="checkbox" id="drdbg-redaction-enabled"' + (settings.redaction_enabled ? ' checked' : '') + '>';
		html += '          <span class="drdbg-toggle-slider"></span>';
		html += '        </label>';
		html += '      </div>';
		html += '    </div>';

		// Hide frontend errors.
		html += '    <div class="drdbg-field">';
		html += '      <div>';
		html += '        <div class="drdbg-field-label">مخفی‌سازی خطاهای پیشخوان</div>';
		html += '        <div class="drdbg-field-desc">نمایش خطاها در پیشخوان سایت برای غیرمدیران غیرفعال می‌شود.</div>';
		html += '      </div>';
		html += '      <div class="drdbg-field-control">';
		html += '        <label class="drdbg-toggle">';
		html += '          <input type="checkbox" id="drdbg-hide-frontend"' + (settings.hide_frontend ? ' checked' : '') + '>';
		html += '          <span class="drdbg-toggle-slider"></span>';
		html += '        </label>';
		html += '      </div>';
		html += '    </div>';

		html += '  </div>';

		// Cleanup section.
		html += '  <div class="drdbg-settings-section">';
		html += '    <h3 class="drdbg-settings-section-title">پاکسازی</h3>';

		html += '    <div class="drdbg-cleanup-row">';
		html += '      <label>پاکسازی خطاهای قدیمی‌تر از</label>';
		html += '      <input type="number" id="drdbg-cleanup-custom-days" value="30" min="1" max="365" style="width:80px;text-align:center;background:var(--drdbg-white);border:none;color:var(--drdbg-text);border-radius:var(--drdbg-radius);padding:9px 12px;box-shadow:var(--drdbg-shadow-input);">';
		html += '      <span>روز</span>';
		html += '      <button class="drdbg-btn drdbg-btn--danger drdbg-btn--small" id="drdbg-cleanup-btn">پاکسازی</button>';
		html += '    </div>';

		html += '    <div class="drdbg-cleanup-row">';
		html += '      <button class="drdbg-btn drdbg-btn--danger" id="drdbg-delete-all-btn">حذف همه خطاها</button>';
		html += '    </div>';

		html += '  </div>';

		// Save button.
		html += '  <div style="display:flex;gap:10px;">';
		html += '    <button class="drdbg-btn drdbg-btn--primary" id="drdbg-save-settings">ذخیره تنظیمات</button>';
		html += '    <button class="drdbg-btn drdbg-btn--ghost" id="drdbg-reset-settings">بازنشانی به پیش‌فرض</button>';
		html += '  </div>';

		html += '</div>';

		$('#drdbg-content').html(html);
		drdbgBindSettingsEvents();
	}

	function drdbgBindSettingsEvents() {
		// Save settings.
		$(document).off('click.drdbg-save', '#drdbg-save-settings').on('click.drdbg-save', '#drdbg-save-settings', function () {
			var settings = {
				cleanup_days: $('#drdbg-cleanup-days').val(),
				max_occurrences: $('#drdbg-max-occurrences').val(),
				min_severity: $('#drdbg-min-severity').val(),
				redaction_enabled: $('#drdbg-redaction-enabled').is(':checked'),
				hide_frontend: $('#drdbg-hide-frontend').is(':checked')
			};
			drdbgSaveSettings(settings);
		});

		// Reset settings.
		$(document).off('click.drdbg-reset', '#drdbg-reset-settings').on('click.drdbg-reset', '#drdbg-reset-settings', function () {
			if (confirm(drdbg.strings.reset_confirm)) {
				var settings = {
					cleanup_days: 30,
					max_occurrences: 20,
					min_severity: 2,
					redaction_enabled: true,
					hide_frontend: true
				};
				drdbgSaveSettings(settings);
			}
		});

		// Cleanup.
		$(document).off('click.drdbg-cleanup', '#drdbg-cleanup-btn').on('click.drdbg-cleanup', '#drdbg-cleanup-btn', function () {
			if (confirm(drdbg.strings.confirm_cleanup)) {
				var days = $('#drdbg-cleanup-custom-days').val();
				drdbgCleanup(days);
			}
		});

		// Delete all.
		$(document).off('click.drdbg-delall', '#drdbg-delete-all-btn').on('click.drdbg-delall', '#drdbg-delete-all-btn', function () {
			if (confirm(drdbg.strings.confirm_delete_all)) {
				drdbgDeleteErrors([]);
			}
		});
	}

	// ─── Action Handlers ────────────────────────────────────────────

	function drdbgUpdateStatus(id, status) {
		$.post(drdbg.ajax_url, {
			action: 'drdbg_update_status',
			nonce: drdbg.nonce,
			id: id,
			status: status
		}, function (res) {
			if (res.success) {
				drdbgToast(drdbg.strings.status_updated, 'success');
			} else {
				drdbgToast(drdbg.strings.error_occurred, 'error');
			}
		}).fail(function () {
			drdbgToast(drdbg.strings.error_occurred, 'error');
		});
	}

	function drdbgBatchUpdate(ids, status) {
		if (!ids.length) return;
		$.post(drdbg.ajax_url, {
			action: 'drdbg_batch_update',
			nonce: drdbg.nonce,
			ids: ids,
			status: status
		}, function (res) {
			if (res.success) {
				drdbgToast(drdbg.strings.batch_updated, 'success');
				drdbgState.selectedIds = [];
				drdbgLoadErrors();
			} else {
				drdbgToast(drdbg.strings.error_occurred, 'error');
			}
		}).fail(function () {
			drdbgToast(drdbg.strings.error_occurred, 'error');
		});
	}

	function drdbgDeleteErrors(ids) {
		$.post(drdbg.ajax_url, {
			action: 'drdbg_delete_errors',
			nonce: drdbg.nonce,
			ids: ids
		}, function (res) {
			if (res.success) {
				drdbgToast(drdbg.strings.deleted, 'success');
				drdbgState.selectedIds = [];
				if (drdbgState.currentView === 'error-detail') {
					drdbgLoadView('errors');
				} else {
					drdbgLoadErrors();
				}
			} else {
				drdbgToast(drdbg.strings.error_occurred, 'error');
			}
		}).fail(function () {
			drdbgToast(drdbg.strings.error_occurred, 'error');
		});
	}

	function drdbgCleanup(days) {
		$.post(drdbg.ajax_url, {
			action: 'drdbg_cleanup',
			nonce: drdbg.nonce,
			days: days
		}, function (res) {
			if (res.success) {
				var deleted = res.data && res.data.deleted ? res.data.deleted : 0;
				drdbgToast(drdbg.strings.cleaned + ' (' + drdbgToPersianDigits(deleted) + ' حذف شد)', 'success');
			} else {
				drdbgToast(drdbg.strings.error_occurred, 'error');
			}
		}).fail(function () {
			drdbgToast(drdbg.strings.error_occurred, 'error');
		});
	}

	function drdbgSaveSettings(settings) {
		$.post(drdbg.ajax_url, {
			action: 'drdbg_update_settings',
			nonce: drdbg.nonce,
			cleanup_days: settings.cleanup_days,
			max_occurrences: settings.max_occurrences,
			min_severity: settings.min_severity,
			redaction_enabled: settings.redaction_enabled,
			hide_frontend: settings.hide_frontend
		}, function (res) {
			if (res.success) {
				drdbgToast(drdbg.strings.saved, 'success');
				drdbgRenderSettings(res.data);
			} else {
				drdbgToast(drdbg.strings.error_occurred, 'error');
			}
		}).fail(function () {
			drdbgToast(drdbg.strings.error_occurred, 'error');
		});
	}

	function drdbgSeedDemo() {
		$.post(drdbg.ajax_url, {
			action: 'drdbg_seed_demo',
			nonce: drdbg.nonce
		}, function (res) {
			if (res.success) {
				var created = res.data && res.data.created ? res.data.created : 0;
				drdbgToast(drdbg.strings.seeded + ' (' + drdbgToPersianDigits(created) + ' خطا ایجاد شد)', 'success');
				// Reload current view.
				drdbgLoadView(drdbgState.currentView);
			} else {
				drdbgToast(drdbg.strings.error_occurred, 'error');
			}
		}).fail(function () {
			drdbgToast(drdbg.strings.error_occurred, 'error');
		});
	}

	// ─── Utility Functions ──────────────────────────────────────────

	/**
	 * Copy button markup.
	 */
	function drdbgCopyButton(extraClass, label, occIndex) {
		var title = label || (drdbg.strings && drdbg.strings.copy) || 'کپی';
		var idxAttr = occIndex !== undefined ? ' data-occ-index="' + occIndex + '"' : '';
		return '<button type="button" class="drdbg-btn drdbg-btn--ghost drdbg-btn--icon drdbg-copy-btn ' + extraClass + '"' + idxAttr + ' title="' + drdbgEscapeAttr(title) + '">' +
			'<span class="dashicons dashicons-admin-page"></span>' +
			'<span class="drdbg-copy-btn-label">' + drdbgEscapeHtml(title) + '</span>' +
			'</button>';
	}

	/**
	 * Copy plain text to the clipboard.
	 */
	function drdbgCopyToClipboard(text) {
		if (!text) {
			drdbgToast(drdbg.strings.copy_failed, 'error');
			return;
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				drdbgToast(drdbg.strings.copied, 'success');
			}).catch(function () {
				drdbgCopyToClipboardFallback(text);
			});
			return;
		}

		drdbgCopyToClipboardFallback(text);
	}

	function drdbgCopyToClipboardFallback(text) {
		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();

		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (err) {
			ok = false;
		}

		document.body.removeChild(textarea);

		if (ok) {
			drdbgToast(drdbg.strings.copied, 'success');
		} else {
			drdbgToast(drdbg.strings.copy_failed, 'error');
		}
	}

	/**
	 * Plain-text summary for an error group.
	 */
	function drdbgFormatErrorSummary(error) {
		if (!error) return '';

		var lines = [
			'=== Dr. Debug — خطا ===',
			'شناسه: ' + (error.id || '—'),
			'پیام: ' + (error.message || '—'),
			'فایل: ' + (error.file || '—'),
			'خط: ' + (error.line || '—'),
			'شدت: ' + (severityLabels[error.severity_level] || '—'),
			'وضعیت: ' + (statusLabels[parseInt(error.status, 10)] || '—'),
			'نوع خطا: ' + drdbgErrorTypeLabel(error.error_type),
			'منبع: ' + (sourceTypeLabels[error.source_type] || '—') + (error.source_slug ? ' (' + error.source_slug + ')' : ''),
			'تعداد تکرار: ' + (error.count || '—'),
			'اولین مشاهده: ' + (error.first_seen || '—'),
			'آخرین مشاهده: ' + (error.last_seen || '—')
		];

		return lines.join('\n');
	}

	/**
	 * Plain-text report for error + occurrences on the current page.
	 */
	function drdbgFormatErrorFull(data) {
		if (!data || !data.error) return '';

		var text = drdbgFormatErrorSummary(data.error);
		var occurrences = data.occurrences || [];
		var total = data.total_occurrences || occurrences.length;

		if (occurrences.length > 0) {
			text += '\n\n--- وقوع‌ها ---';
			for (var i = 0; i < occurrences.length; i++) {
				text += '\n\n' + drdbgFormatOccurrence(data.error, occurrences[i], i + 1);
			}
		}

		if (total > occurrences.length) {
			text += '\n\n(نمایش ' + occurrences.length + ' از ' + total + ' وقوع — برای بقیه صفحه بعد را باز کنید)';
		}

		return text;
	}

	/**
	 * Plain-text block for a single occurrence.
	 */
	function drdbgFormatOccurrence(error, occ, number) {
		if (!occ) return '';

		var lines = [
			'--- وقوع #' + (number || 1) + ' ---',
			'پیام: ' + (error && error.message ? error.message : '—'),
			'زمان: ' + (occ.occurred_at || '—'),
			'زمینه: ' + (contextTypeLabels[occ.context_type] || '—')
		];

		if (occ.request_url) {
			lines.push('URL: ' + occ.request_url);
		}
		if (occ.request_method) {
			lines.push('متد: ' + occ.request_method);
		}
		if (occ.memory_peak) {
			lines.push('حافظه: ' + drdbgFormatMemory(occ.memory_peak));
		}
		if (occ.php_version) {
			lines.push('PHP: ' + occ.php_version);
		}
		if (occ.wp_version) {
			lines.push('WordPress: ' + occ.wp_version);
		}
		if (error && error.file) {
			lines.push('فایل: ' + error.file + ':' + (error.line || '—'));
		}
		if (occ.stack_trace) {
			lines.push('', 'Stack trace:', occ.stack_trace);
		}

		return lines.join('\n');
	}

	/**
	 * Convert digits to Persian (فارسی) numerals.
	 */
	function drdbgToPersianDigits(num) {
		if (num === null || num === undefined) return '';
		var str = String(num);
		var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		return str.replace(/[0-9]/g, function (d) {
			return persianDigits[parseInt(d, 10)];
		});
	}

	/**
	 * Relative time in Persian.
	 */
	function drdbgRelativeTime(dateStr) {
		if (!dateStr) return '—';
		var now = new Date();
		var date = new Date(dateStr + 'Z'); // Treat as UTC.
		var diff = Math.floor((now - date) / 1000);

		if (diff < 0) diff = 0;

		if (diff < 60) return drdbgToPersianDigits(diff) + ' ثانیه پیش';
		if (diff < 3600) return drdbgToPersianDigits(Math.floor(diff / 60)) + ' دقیقه پیش';
		if (diff < 86400) return drdbgToPersianDigits(Math.floor(diff / 3600)) + ' ساعت پیش';
		if (diff < 2592000) return drdbgToPersianDigits(Math.floor(diff / 86400)) + ' روز پیش';
		if (diff < 31536000) return drdbgToPersianDigits(Math.floor(diff / 2592000)) + ' ماه پیش';
		return drdbgToPersianDigits(Math.floor(diff / 31536000)) + ' سال پیش';
	}

	/**
	 * Severity badge HTML.
	 */
	function drdbgSeverityBadge(level) {
		var label = severityLabels[level] || 'نامشخص';
		var color = severityColors[level] || 'info';
		return '<span class="drdbg-badge drdbg-badge--' + color + '">' + label + '</span>';
	}

	/**
	 * Status badge HTML.
	 */
	function drdbgStatusBadge(status) {
		var s = parseInt(status, 10);
		var label = statusLabels[s] || 'نامشخص';
		var color = statusColors[s] || 'new';
		return '<span class="drdbg-badge drdbg-badge--' + color + '">' + label + '</span>';
	}

	/**
	 * Source type badge HTML.
	 */
	function drdbgSourceTypeBadge(type) {
		var t = parseInt(type, 10);
		var label = sourceTypeLabels[t] || 'نامشخص';
		var color = sourceTypeColors[t] || 'unknown';
		return '<span class="drdbg-badge drdbg-badge--' + color + '">' + label + '</span>';
	}

	/**
	 * Context type label.
	 */
	function drdbgContextTypeLabel(type) {
		return contextTypeLabels[type] || '—';
	}

	/**
	 * Error type label (E_ERROR, E_WARNING, etc).
	 */
	function drdbgErrorTypeLabel(errorType) {
		var map = {
			1: 'E_ERROR',
			2: 'E_WARNING',
			4: 'E_PARSE',
			8: 'E_NOTICE',
			16: 'E_CORE_ERROR',
			32: 'E_CORE_WARNING',
			64: 'E_COMPILE_ERROR',
			128: 'E_COMPILE_WARNING',
			256: 'E_USER_ERROR',
			512: 'E_USER_WARNING',
			1024: 'E_USER_NOTICE',
			2048: 'E_STRICT',
			4096: 'E_RECOVERABLE_ERROR',
			8192: 'E_DEPRECATED',
			16384: 'E_USER_DEPRECATED'
		};
		return map[errorType] || 'E_UNKNOWN';
	}

	/**
	 * Format bytes to human-readable memory.
	 */
	function drdbgFormatMemory(bytes) {
		if (!bytes) return '—';
		if (bytes < 1024) return drdbgToPersianDigits(bytes) + ' B';
		if (bytes < 1048576) return drdbgToPersianDigits((bytes / 1024).toFixed(1)) + ' KB';
		return drdbgToPersianDigits((bytes / 1048576).toFixed(1)) + ' MB';
	}

	/**
	 * Escape HTML entities.
	 */
	function drdbgEscapeHtml(str) {
		if (!str) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	/**
	 * Escape for HTML attributes.
	 */
	function drdbgEscapeAttr(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	/**
	 * Toast notification.
	 */
	function drdbgToast(message, type) {
		type = type || 'info';
		$('.drdbg-toast').remove();
		var $toast = $('<div class="drdbg-toast drdbg-toast--' + type + '">' + message + '</div>');
		$('body').append($toast);
		setTimeout(function () { $toast.addClass('visible'); }, 50);
		setTimeout(function () {
			$toast.removeClass('visible');
			setTimeout(function () { $toast.remove(); }, 300);
		}, 3000);
	}

	/**
	 * Empty state HTML.
	 */
	function drdbgEmptyState(title, desc, icon) {
		return '<div class="drdbg-empty">' +
			'<div class="drdbg-empty-icon">' + (icon || '🔍') + '</div>' +
			'<h3 class="drdbg-empty-title">' + title + '</h3>' +
			'<p class="drdbg-empty-desc">' + desc + '</p>' +
			'</div>';
	}

	// ─── Skeleton Loaders ───────────────────────────────────────────

	function drdbgSkeletonDashboard() {
		return '<div class="drdbg-stats-grid">' +
			'<div class="drdbg-skeleton drdbg-skeleton--card"></div>'.repeat(4) +
			'</div>' +
			'<div class="drdbg-two-col">' +
			'<div class="drdbg-skeleton drdbg-skeleton--card" style="height:240px;"></div>' +
			'<div class="drdbg-skeleton drdbg-skeleton--card" style="height:240px;"></div>' +
			'</div>';
	}

	function drdbgSkeletonList() {
		var rows = '';
		for (var i = 0; i < 8; i++) {
			rows += '<div class="drdbg-skeleton drdbg-skeleton--row"></div>';
		}
		return '<div class="drdbg-skeleton drdbg-skeleton--row" style="height:40px;margin-bottom:16px;"></div>' + rows;
	}

	function drdbgSkeletonDetail() {
		return '<div class="drdbg-skeleton drdbg-skeleton--title"></div>' +
			'<div class="drdbg-skeleton drdbg-skeleton--card" style="height:200px;margin-bottom:16px;"></div>' +
			'<div class="drdbg-skeleton drdbg-skeleton--card" style="height:300px;"></div>';
	}

	function drdbgSkeletonSettings() {
		return '<div class="drdbg-skeleton drdbg-skeleton--card" style="height:400px;max-width:700px;"></div>';
	}

})(jQuery);
