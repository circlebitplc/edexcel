<?php
declare(strict_types=1);

/**
 * admin/views/site_visitors_tab.php
 * 
 * Site Visitors Analytics Dashboard View.
 * Rendered inside the "Site Visitors" tab of Admin Settings.
 */

require_once __DIR__ . '/../../src/Services/SiteVisitorAnalyticsService.php';

use Edexcel\Services\SiteVisitorAnalyticsService;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo '<div class="alert alert-warning">Database connection is unavailable for visitor analytics.</div>';
    return;
}

SiteVisitorAnalyticsService::ensureSchema($pdo);

// Parse date range filter
$preset = (string)($_GET['vrange'] ?? '7d');
$now = new DateTime();
$todayStr = $now->format('Y-m-d');

switch ($preset) {
    case 'today':
        $from = $todayStr;
        $to = $todayStr;
        break;
    case 'yesterday':
        $from = (new DateTime('-1 day'))->format('Y-m-d');
        $to = $from;
        break;
    case '30d':
        $from = (new DateTime('-29 days'))->format('Y-m-d');
        $to = $todayStr;
        break;
    case 'month':
        $from = $now->format('Y-m-01');
        $to = $todayStr;
        break;
    case 'custom':
        $from = (string)($_GET['vfrom'] ?? (new DateTime('-6 days'))->format('Y-m-d'));
        $to = (string)($_GET['vto'] ?? $todayStr);
        break;
    case '7d':
    default:
        $preset = '7d';
        $from = (new DateTime('-6 days'))->format('Y-m-d');
        $to = $todayStr;
        break;
}

if ($from > $to) {
    $temp = $from;
    $from = $to;
    $to = $temp;
}

$activeVisitors = SiteVisitorAnalyticsService::getRealtimeActiveCount($pdo, 5);
$stats = SiteVisitorAnalyticsService::getDashboardStats($pdo, $from, $to);
$timeline = SiteVisitorAnalyticsService::getTimeline($pdo, $from, $to);
$topPages = SiteVisitorAnalyticsService::getTopPages($pdo, $from, $to, 10);
$topEntry = SiteVisitorAnalyticsService::getTopEntryPages($pdo, $from, $to, 8);
$topExit = SiteVisitorAnalyticsService::getTopExitPages($pdo, $from, $to, 8);
$trafficSources = SiteVisitorAnalyticsService::getTrafficSources($pdo, $from, $to);
$deviceStats = SiteVisitorAnalyticsService::getDeviceBreakdown($pdo, $from, $to);
$browserOs = SiteVisitorAnalyticsService::getBrowserAndOsStats($pdo, $from, $to, 6);
$locations = SiteVisitorAnalyticsService::getLocationStats($pdo, $from, $to, 8);

// Detailed session explorer filter & pagination
$sessPage = max(1, (int)($_GET['spage'] ?? 1));
$sessFilter = [
    'from' => $from,
    'to' => $to,
    'q' => trim((string)($_GET['sq'] ?? '')),
    'device_type' => trim((string)($_GET['sdevice'] ?? '')),
    'traffic_source' => trim((string)($_GET['ssource'] ?? '')),
];
$sessions = SiteVisitorAnalyticsService::getSessions($pdo, $sessFilter, $sessPage, 15);

$exportCsvUrl = BASE_URL . "admin/export_visitor_analytics.php?type=csv&from=" . urlencode($from) . "&to=" . urlencode($to);
$exportPdfUrl = BASE_URL . "admin/export_visitor_analytics.php?type=pdf&from=" . urlencode($from) . "&to=" . urlencode($to);
?>

<div class="site-visitors-dashboard">
    <!-- Header Controls: Live Badge, Date Presets & Export -->
    <div class="visitors-header-bar">
        <div class="visitors-title-block">
            <h2><i class="bi bi-graph-up-arrow text-primary"></i> Site Visitors Analytics</h2>
            <p class="text-muted">Privacy-respecting website traffic, real-time visitors, engagement, and audience demographics.</p>
        </div>

        <div class="visitors-header-actions">
            <!-- Real-time Live Badge -->
            <div class="live-active-pill" id="liveActivePill" title="Visitors active on the website in the last 5 minutes">
                <span class="live-pulse-dot"></span>
                <span id="activeVisitorsCount" class="live-count"><?= (int)$activeVisitors ?></span>
                <span class="live-label">active now</span>
            </div>

            <!-- Export Buttons -->
            <div class="btn-group btn-group-sm">
                <a href="<?= htmlspecialchars($exportCsvUrl) ?>" class="btn btn-outline-secondary" download title="Download CSV Report">
                    <i class="bi bi-file-earmark-spreadsheet"></i> CSV
                </a>
                <a href="<?= htmlspecialchars($exportPdfUrl) ?>" target="_blank" class="btn btn-outline-secondary" title="Export PDF / Print View">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Date Range Filter Bar -->
    <div class="visitors-filter-card">
        <div class="date-preset-pills">
            <a href="?tab=site_visitors&vrange=today" class="date-pill <?= $preset === 'today' ? 'is-active' : '' ?>">Today</a>
            <a href="?tab=site_visitors&vrange=yesterday" class="date-pill <?= $preset === 'yesterday' ? 'is-active' : '' ?>">Yesterday</a>
            <a href="?tab=site_visitors&vrange=7d" class="date-pill <?= $preset === '7d' ? 'is-active' : '' ?>">Last 7 Days</a>
            <a href="?tab=site_visitors&vrange=30d" class="date-pill <?= $preset === '30d' ? 'is-active' : '' ?>">Last 30 Days</a>
            <a href="?tab=site_visitors&vrange=month" class="date-pill <?= $preset === 'month' ? 'is-active' : '' ?>">This Month</a>
            <button type="button" class="date-pill <?= $preset === 'custom' ? 'is-active' : '' ?>" id="btnToggleCustomDate">
                <i class="bi bi-calendar3"></i> Custom
            </button>
        </div>

        <!-- Custom Date Range Form -->
        <form method="GET" class="custom-date-form <?= $preset === 'custom' ? '' : 'd-none' ?>" id="customDateForm">
            <input type="hidden" name="tab" value="site_visitors">
            <input type="hidden" name="vrange" value="custom">
            <div class="input-group input-group-sm">
                <span class="input-group-text">From</span>
                <input type="date" class="form-control" name="vfrom" value="<?= htmlspecialchars($from) ?>" required>
                <span class="input-group-text">To</span>
                <input type="date" class="form-control" name="vto" value="<?= htmlspecialchars($to) ?>" required>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>

        <div class="date-range-display text-muted small">
            <i class="bi bi-clock-history"></i>
            <span><?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></span>
            <span class="badge bg-secondary-subtle text-secondary ms-1"><?= (int)$stats['days'] ?> days</span>
            <span class="ms-1">(compared to previous <?= (int)$stats['days'] ?> days: <?= htmlspecialchars($stats['prev_from']) ?> to <?= htmlspecialchars($stats['prev_to']) ?>)</span>
        </div>
    </div>

    <!-- 1. Executive Summary KPI Cards -->
    <div class="visitors-kpi-grid">
        <!-- Total Visits -->
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label">Total Visits</span>
                <div class="kpi-icon-wrap bg-primary-subtle text-primary"><i class="bi bi-eye"></i></div>
            </div>
            <div class="kpi-body">
                <div class="kpi-val"><?= number_format($stats['current']['visits']) ?></div>
                <div class="kpi-meta">
                    <?php $vc = $stats['change']['visits']; ?>
                    <span class="kpi-badge <?= $vc > 0 ? 'badge-up' : ($vc < 0 ? 'badge-down' : 'badge-neutral') ?>">
                        <i class="bi <?= $vc > 0 ? 'bi-arrow-up-right' : ($vc < 0 ? 'bi-arrow-down-right' : 'bi-dash') ?>"></i>
                        <?= $vc > 0 ? '+' : '' ?><?= $vc ?>%
                    </span>
                    <span class="kpi-prev">vs <?= number_format($stats['previous']['visits']) ?></span>
                </div>
            </div>
        </div>

        <!-- Unique Visitors -->
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label">Unique Visitors</span>
                <div class="kpi-icon-wrap bg-success-subtle text-success"><i class="bi bi-people"></i></div>
            </div>
            <div class="kpi-body">
                <div class="kpi-val"><?= number_format($stats['current']['unique_visitors']) ?></div>
                <div class="kpi-meta">
                    <?php $uvc = $stats['change']['unique_visitors']; ?>
                    <span class="kpi-badge <?= $uvc > 0 ? 'badge-up' : ($uvc < 0 ? 'badge-down' : 'badge-neutral') ?>">
                        <i class="bi <?= $uvc > 0 ? 'bi-arrow-up-right' : ($uvc < 0 ? 'bi-arrow-down-right' : 'bi-dash') ?>"></i>
                        <?= $uvc > 0 ? '+' : '' ?><?= $uvc ?>%
                    </span>
                    <span class="kpi-prev">vs <?= number_format($stats['previous']['unique_visitors']) ?></span>
                </div>
            </div>
        </div>

        <!-- Pageviews -->
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label">Total Pageviews</span>
                <div class="kpi-icon-wrap bg-info-subtle text-info"><i class="bi bi-files"></i></div>
            </div>
            <div class="kpi-body">
                <div class="kpi-val"><?= number_format($stats['current']['pageviews']) ?></div>
                <div class="kpi-meta">
                    <?php $pvc = $stats['change']['pageviews']; ?>
                    <span class="kpi-badge <?= $pvc > 0 ? 'badge-up' : ($pvc < 0 ? 'badge-down' : 'badge-neutral') ?>">
                        <i class="bi <?= $pvc > 0 ? 'bi-arrow-up-right' : ($pvc < 0 ? 'bi-arrow-down-right' : 'bi-dash') ?>"></i>
                        <?= $pvc > 0 ? '+' : '' ?><?= $pvc ?>%
                    </span>
                    <span class="kpi-prev">vs <?= number_format($stats['previous']['pageviews']) ?></span>
                </div>
            </div>
        </div>

        <!-- Avg Session Duration -->
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label">Avg Duration</span>
                <div class="kpi-icon-wrap bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="kpi-body">
                <div class="kpi-val"><?= SiteVisitorAnalyticsService::formatDuration($stats['current']['avg_duration']) ?></div>
                <div class="kpi-meta">
                    <?php $dc = $stats['change']['avg_duration']; ?>
                    <span class="kpi-badge <?= $dc > 0 ? 'badge-up' : ($dc < 0 ? 'badge-down' : 'badge-neutral') ?>">
                        <i class="bi <?= $dc > 0 ? 'bi-arrow-up-right' : ($dc < 0 ? 'bi-arrow-down-right' : 'bi-dash') ?>"></i>
                        <?= $dc > 0 ? '+' : '' ?><?= $dc ?>%
                    </span>
                    <span class="kpi-prev">vs <?= SiteVisitorAnalyticsService::formatDuration($stats['previous']['avg_duration']) ?></span>
                </div>
            </div>
        </div>

        <!-- Bounce Rate -->
        <div class="kpi-card">
            <div class="kpi-head">
                <span class="kpi-label">Bounce Rate</span>
                <div class="kpi-icon-wrap bg-danger-subtle text-danger"><i class="bi bi-box-arrow-up-right"></i></div>
            </div>
            <div class="kpi-body">
                <div class="kpi-val"><?= $stats['current']['bounce_rate'] ?>%</div>
                <div class="kpi-meta">
                    <?php $brc = $stats['change']['bounce_rate']; ?>
                    <span class="kpi-badge <?= $brc < 0 ? 'badge-up' : ($brc > 0 ? 'badge-down' : 'badge-neutral') ?>">
                        <i class="bi <?= $brc < 0 ? 'bi-arrow-down-right' : ($brc > 0 ? 'bi-arrow-up-right' : 'bi-dash') ?>"></i>
                        <?= $brc > 0 ? '+' : '' ?><?= $brc ?> pts
                    </span>
                    <span class="kpi-prev">vs <?= $stats['previous']['bounce_rate'] ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Main Timeline Chart -->
    <div class="visitors-card mb-4">
        <div class="visitors-card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="h6 mb-0 fw-bold"><i class="bi bi-bar-chart-line text-primary me-2"></i> Visitor &amp; Pageview Trends</h3>
                <small class="text-muted">Daily traffic dynamics over the selected period</small>
            </div>
            <div class="chart-legend-wrap">
                <span class="legend-indicator" style="background:#4f46e5;"></span> Visits
                <span class="legend-indicator ms-3" style="background:#06b6d4;"></span> Pageviews
            </div>
        </div>
        <div class="visitors-card-body">
            <div class="chart-container" style="position: relative; height: 260px; width: 100%;">
                <canvas id="visitorTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- 3. Two Columns: Top Pages & Entry/Exit Pages -->
    <div class="row g-3 mb-4">
        <!-- Most Visited Pages -->
        <div class="col-lg-7">
            <div class="visitors-card h-100">
                <div class="visitors-card-header">
                    <h3 class="h6 mb-0 fw-bold"><i class="bi bi-file-earmark-text text-primary me-2"></i> Most Visited Pages</h3>
                    <small class="text-muted">Pages with the highest traffic and reader engagement</small>
                </div>
                <div class="visitors-card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped-columns align-middle mb-0 analytics-table">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Page</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">Unique</th>
                                    <th style="width: 25%;" class="text-end">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topPages)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No pageviews recorded yet for this date range.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($topPages as $page): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($page['page_title']) ?>">
                                                <?= htmlspecialchars($page['page_title']) ?>
                                            </div>
                                            <div class="small text-muted font-monospace text-truncate" style="max-width: 280px;">
                                                <?= htmlspecialchars($page['page_path']) ?>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold"><?= number_format($page['views']) ?></td>
                                        <td class="text-end text-muted"><?= number_format($page['unique_visitors']) ?></td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $page['percentage'] ?>%;"></div>
                                                </div>
                                                <span class="small text-muted" style="min-width: 35px;"><?= $page['percentage'] ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Entry & Exit Pages -->
        <div class="col-lg-5">
            <div class="visitors-card h-100">
                <div class="visitors-card-header d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0 fw-bold"><i class="bi bi-door-open text-primary me-2"></i> Entry &amp; Exit Pages</h3>
                    <ul class="nav nav-pills nav-pills-sm" id="entryExitTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-2 small" id="tab-entry-btn" data-bs-toggle="pill" data-bs-target="#tab-entry" type="button" role="tab">Entry</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2 small" id="tab-exit-btn" data-bs-toggle="pill" data-bs-target="#tab-exit" type="button" role="tab">Exit</button>
                        </li>
                    </ul>
                </div>
                <div class="visitors-card-body p-0">
                    <div class="tab-content" id="entryExitContent">
                        <!-- Entry Pages -->
                        <div class="tab-pane fade show active" id="tab-entry" role="tabpanel">
                            <table class="table table-hover align-middle mb-0 analytics-table">
                                <thead>
                                    <tr>
                                        <th>Entry URL</th>
                                        <th class="text-end">Sessions</th>
                                        <th class="text-end" style="width: 28%;">Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($topEntry)): ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">No entry data available.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($topEntry as $ent): ?>
                                        <tr>
                                            <td class="font-monospace text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($ent['entry_page']) ?>">
                                                <?= htmlspecialchars($ent['entry_page']) ?>
                                            </td>
                                            <td class="text-end fw-bold"><?= number_format($ent['count']) ?></td>
                                            <td class="text-end">
                                                <div class="d-flex align-items-center justify-content-end gap-1">
                                                    <div class="progress flex-grow-1" style="height: 5px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $ent['percentage'] ?>%;"></div>
                                                    </div>
                                                    <span class="small text-muted" style="min-width: 32px;"><?= $ent['percentage'] ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Exit Pages -->
                        <div class="tab-pane fade" id="tab-exit" role="tabpanel">
                            <table class="table table-hover align-middle mb-0 analytics-table">
                                <thead>
                                    <tr>
                                        <th>Exit URL</th>
                                        <th class="text-end">Drop-offs</th>
                                        <th class="text-end" style="width: 28%;">Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($topExit)): ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">No exit data available.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($topExit as $ext): ?>
                                        <tr>
                                            <td class="font-monospace text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($ext['exit_page']) ?>">
                                                <?= htmlspecialchars($ext['exit_page']) ?>
                                            </td>
                                            <td class="text-end fw-bold"><?= number_format($ext['count']) ?></td>
                                            <td class="text-end">
                                                <div class="d-flex align-items-center justify-content-end gap-1">
                                                    <div class="progress flex-grow-1" style="height: 5px;">
                                                        <div class="progress-bar bg-danger" style="width: <?= $ext['percentage'] ?>%;"></div>
                                                    </div>
                                                    <span class="small text-muted" style="min-width: 32px;"><?= $ext['percentage'] ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Traffic Sources, Devices, Browsers & Locations Grid -->
    <div class="row g-3 mb-4">
        <!-- Traffic Sources -->
        <div class="col-lg-4 col-md-6">
            <div class="visitors-card h-100">
                <div class="visitors-card-header">
                    <h3 class="h6 mb-0 fw-bold"><i class="bi bi-funnel text-primary me-2"></i> Traffic Sources</h3>
                    <small class="text-muted">How visitors discovered your site</small>
                </div>
                <div class="visitors-card-body">
                    <div class="chart-container mb-3" style="position: relative; height: 160px;">
                        <canvas id="trafficSourcesChart"></canvas>
                    </div>
                    <div class="traffic-sources-list">
                        <?php foreach ($trafficSources as $src): ?>
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                            <span class="small d-flex align-items-center gap-2">
                                <i class="bi <?= htmlspecialchars($src['icon']) ?> text-muted"></i>
                                <?= htmlspecialchars($src['label']) ?>
                            </span>
                            <span class="small">
                                <strong><?= number_format($src['count']) ?></strong>
                                <span class="text-muted ms-1">(<?= $src['percentage'] ?>%)</span>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Devices -->
        <div class="col-lg-4 col-md-6">
            <div class="visitors-card h-100">
                <div class="visitors-card-header">
                    <h3 class="h6 mb-0 fw-bold"><i class="bi bi-laptop text-primary me-2"></i> Device Types</h3>
                    <small class="text-muted">Desktop, Mobile and Tablet distribution</small>
                </div>
                <div class="visitors-card-body">
                    <div class="chart-container mb-3" style="position: relative; height: 160px;">
                        <canvas id="deviceChart"></canvas>
                    </div>
                    <div class="device-stats-grid">
                        <div class="device-stat-box text-center p-2 rounded bg-body-tertiary">
                            <i class="bi bi-display fs-5 text-primary"></i>
                            <div class="small fw-bold mt-1">Desktop</div>
                            <div class="fw-bold"><?= $deviceStats['desktop']['count'] ?></div>
                            <small class="text-muted"><?= $deviceStats['desktop']['percentage'] ?>%</small>
                        </div>
                        <div class="device-stat-box text-center p-2 rounded bg-body-tertiary">
                            <i class="bi bi-phone fs-5 text-success"></i>
                            <div class="small fw-bold mt-1">Mobile</div>
                            <div class="fw-bold"><?= $deviceStats['mobile']['count'] ?></div>
                            <small class="text-muted"><?= $deviceStats['mobile']['percentage'] ?>%</small>
                        </div>
                        <div class="device-stat-box text-center p-2 rounded bg-body-tertiary">
                            <i class="bi bi-tablet fs-5 text-info"></i>
                            <div class="small fw-bold mt-1">Tablet</div>
                            <div class="fw-bold"><?= $deviceStats['tablet']['count'] ?></div>
                            <small class="text-muted"><?= $deviceStats['tablet']['percentage'] ?>%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Locations & Geo -->
        <div class="col-lg-4 col-md-12">
            <div class="visitors-card h-100">
                <div class="visitors-card-header">
                    <h3 class="h6 mb-0 fw-bold"><i class="bi bi-geo-alt text-primary me-2"></i> Visitor Locations</h3>
                    <small class="text-muted">Top countries and visitor cities</small>
                </div>
                <div class="visitors-card-body p-0">
                    <div class="p-3 pb-1 border-bottom">
                        <div class="small fw-bold text-uppercase text-muted mb-2">Top Countries</div>
                        <?php if (empty($locations['countries'])): ?>
                            <div class="small text-muted py-2">No country data recorded yet.</div>
                        <?php else: ?>
                            <?php foreach ($locations['countries'] as $c): ?>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small d-flex align-items-center gap-2">
                                    <span><?= $c['flag'] ?></span>
                                    <span><?= htmlspecialchars($c['name']) ?></span>
                                </span>
                                <span class="small text-muted">
                                    <strong class="text-body"><?= number_format($c['count']) ?></strong> (<?= $c['percentage'] ?>%)
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($locations['cities'])): ?>
                    <div class="p-3 pt-2">
                        <div class="small fw-bold text-uppercase text-muted mb-2">Top Cities</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($locations['cities'] as $city): ?>
                            <span class="badge bg-body-tertiary text-body border">
                                <i class="bi bi-pin-map text-primary"></i> <?= htmlspecialchars($city['city']) ?> (<?= $city['count'] ?>)
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Detailed Visitor Sessions Explorer -->
    <div class="visitors-card">
        <div class="visitors-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="h6 mb-0 fw-bold"><i class="bi bi-list-ul text-primary me-2"></i> Visitor Sessions Explorer</h3>
                <small class="text-muted">Detailed log of recent visits with search and filters</small>
            </div>
            <!-- Search and Filter Form -->
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap" id="sessionFilterForm">
                <input type="hidden" name="tab" value="site_visitors">
                <input type="hidden" name="vrange" value="<?= htmlspecialchars($preset) ?>">
                <?php if ($preset === 'custom'): ?>
                    <input type="hidden" name="vfrom" value="<?= htmlspecialchars($from) ?>">
                    <input type="hidden" name="vto" value="<?= htmlspecialchars($to) ?>">
                <?php endif; ?>

                <div class="input-group input-group-sm" style="max-width: 240px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="sq" placeholder="Search user, email, role, page..." value="<?= htmlspecialchars($sessFilter['q']) ?>">
                </div>

                <select class="form-select form-select-sm" name="sdevice" style="max-width: 120px;" onchange="this.form.submit()">
                    <option value="">All Devices</option>
                    <option value="desktop" <?= $sessFilter['device_type'] === 'desktop' ? 'selected' : '' ?>>Desktop</option>
                    <option value="mobile" <?= $sessFilter['device_type'] === 'mobile' ? 'selected' : '' ?>>Mobile</option>
                    <option value="tablet" <?= $sessFilter['device_type'] === 'tablet' ? 'selected' : '' ?>>Tablet</option>
                </select>

                <select class="form-select form-select-sm" name="ssource" style="max-width: 130px;" onchange="this.form.submit()">
                    <option value="">All Sources</option>
                    <option value="direct" <?= $sessFilter['traffic_source'] === 'direct' ? 'selected' : '' ?>>Direct</option>
                    <option value="google" <?= $sessFilter['traffic_source'] === 'google' ? 'selected' : '' ?>>Google</option>
                    <option value="social" <?= $sessFilter['traffic_source'] === 'social' ? 'selected' : '' ?>>Social</option>
                    <option value="referral" <?= $sessFilter['traffic_source'] === 'referral' ? 'selected' : '' ?>>Referral</option>
                </select>

                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <?php if ($sessFilter['q'] !== '' || $sessFilter['device_type'] !== '' || $sessFilter['traffic_source'] !== ''): ?>
                    <a href="?tab=site_visitors&vrange=<?= htmlspecialchars($preset) ?>" class="btn btn-sm btn-outline-secondary" title="Clear search">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="visitors-card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sessions-table">
                    <thead>
                        <tr>
                            <th>Visitor / User</th>
                            <th>Status &amp; Time</th>
                            <th>Location</th>
                            <th>Device &amp; Browser</th>
                            <th>Source</th>
                            <th>Entry &amp; Exit</th>
                            <th class="text-center">Pages</th>
                            <th class="text-end">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions['records'])): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No visitor sessions match your current criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sessions['records'] as $s): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($s['is_registered'])): ?>
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <span class="badge <?= htmlspecialchars($s['role_badge_class']) ?>" style="font-size: 0.72rem;">
                                                    <i class="bi bi-person-check-fill me-1"></i><?= htmlspecialchars($s['display_role']) ?>
                                                </span>
                                            </div>
                                            <div class="fw-bold text-truncate" style="max-width: 220px;">
                                                <?php if (!empty($s['profile_url'])): ?>
                                                    <a href="<?= htmlspecialchars(BASE_URL . 'admin/' . $s['profile_url']) ?>" class="text-decoration-none text-primary" title="View in Admin">
                                                        <?= htmlspecialchars($s['display_name']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($s['display_name']) ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($s['display_email'])): ?>
                                                <div class="small text-muted text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($s['display_email']) ?>">
                                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($s['display_email']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex flex-column">
                                            <div class="mb-1">
                                                <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.72rem;">
                                                    <i class="bi bi-person me-1"></i>Guest Visitor
                                                </span>
                                            </div>
                                            <span class="text-muted font-monospace" style="font-size: 0.74rem;" title="Anonymous Visitor ID">
                                                ID: <?= substr($s['visitor_id'], 0, 8) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="mb-1">
                                        <?php if (!empty($s['is_online'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                                <span class="live-pulse-dot" style="width: 6px; height: 6px;"></span> Online now
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-body-tertiary text-muted border" style="font-size: 0.72rem;">
                                                <i class="bi bi-clock-history me-1"></i><?= htmlspecialchars($s['activity_status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted" title="First seen at <?= htmlspecialchars($s['first_seen_at']) ?>">
                                        <?= htmlspecialchars($s['first_seen_time']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <span><?= $s['country_flag'] ?></span>
                                        <span class="small fw-semibold"><?= htmlspecialchars($s['country_name']) ?></span>
                                    </div>
                                    <?php if (!empty($s['city'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($s['city']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small d-flex align-items-center gap-1">
                                        <i class="bi <?= $s['device_type'] === 'mobile' ? 'bi-phone' : ($s['device_type'] === 'tablet' ? 'bi-tablet' : 'bi-display') ?>"></i>
                                        <strong><?= ucfirst($s['device_type']) ?></strong>
                                    </div>
                                    <div class="small text-muted"><?= htmlspecialchars($s['browser'] ?: 'Unknown') ?> · <?= htmlspecialchars($s['os'] ?: 'Unknown') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-body-secondary text-body border small">
                                        <?= ucfirst((string)$s['traffic_source']) ?>
                                    </span>
                                    <?php if (!empty($s['referrer_host']) && $s['referrer_host'] !== 'direct'): ?>
                                        <div class="small text-muted font-monospace text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($s['referrer_host']) ?>">
                                            <?= htmlspecialchars($s['referrer_host']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-truncate font-monospace" style="max-width: 200px;" title="Entry: <?= htmlspecialchars($s['entry_page']) ?>">
                                        <i class="bi bi-box-arrow-in-right text-success me-1"></i><?= htmlspecialchars($s['entry_page']) ?>
                                    </div>
                                    <?php if (!empty($s['exit_page']) && $s['exit_page'] !== $s['entry_page']): ?>
                                    <div class="small text-truncate font-monospace text-muted" style="max-width: 200px;" title="Exit: <?= htmlspecialchars($s['exit_page']) ?>">
                                        <i class="bi bi-box-arrow-right text-danger me-1"></i><?= htmlspecialchars($s['exit_page']) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill <?= (int)$s['pageviews_count'] > 1 ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' ?>">
                                        <?= (int)$s['pageviews_count'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold small">
                                    <?= SiteVisitorAnalyticsService::formatDuration((int)$s['duration_seconds']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($sessions['total_pages'] > 1): ?>
            <div class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2">
                <small class="text-muted">
                    Showing page <?= $sessions['page'] ?> of <?= $sessions['total_pages'] ?> (<?= number_format($sessions['total']) ?> total sessions)
                </small>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $qs = http_build_query(array_merge($_GET, ['spage' => max(1, $sessions['page'] - 1)]));
                    ?>
                    <li class="page-item <?= $sessions['page'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars($qs) ?>">&laquo; Prev</a>
                    </li>
                    <?php
                    $startP = max(1, $sessions['page'] - 2);
                    $endP = min($sessions['total_pages'], $startP + 4);
                    for ($p = $startP; $p <= $endP; $p++):
                        $pageQs = http_build_query(array_merge($_GET, ['spage' => $p]));
                    ?>
                        <li class="page-item <?= $sessions['page'] === $p ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars($pageQs) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php
                    $nextQs = http_build_query(array_merge($_GET, ['spage' => min($sessions['total_pages'], $sessions['page'] + 1)]));
                    ?>
                    <li class="page-item <?= $sessions['page'] >= $sessions['total_pages'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars($nextQs) ?>">Next &raquo;</a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Scoped Styles for Site Visitors Dashboard */
.site-visitors-dashboard {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.visitors-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--panel-border, rgba(0, 0, 0, 0.08));
}
.visitors-title-block h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.visitors-title-block p {
    margin: 0.25rem 0 0;
    font-size: 0.85rem;
}
.visitors-header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.live-active-pill {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.3);
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.82rem;
}
.live-pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #10b981;
    box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
    animation: livePulse 2s infinite;
}
@keyframes livePulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.live-count {
    font-weight: 800;
    color: #10b981;
}
.live-label {
    font-size: 0.76rem;
    color: var(--muted, #6c757d);
    font-weight: 600;
    text-transform: uppercase;
}
.visitors-filter-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    background: var(--surface, #fff);
    border: 1px solid var(--panel-border, rgba(0, 0, 0, 0.08));
    border-radius: 12px;
    padding: 0.65rem 1rem;
}
.date-preset-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.date-pill {
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--text, #334155);
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
}
.date-pill:hover {
    background: var(--surface-soft, rgba(0, 0, 0, 0.04));
}
.date-pill.is-active {
    background: var(--primary-soft, rgba(81, 97, 206, 0.12));
    border-color: rgba(81, 97, 206, 0.25);
    color: var(--primary, #5161ce);
}
.visitors-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.85rem;
}
.kpi-card {
    background: var(--surface, #fff);
    border: 1px solid var(--panel-border, rgba(0, 0, 0, 0.08));
    border-radius: 14px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.kpi-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.kpi-label {
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--muted, #6c757d);
    letter-spacing: 0.03em;
}
.kpi-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.kpi-val {
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--text, #0f172a);
}
.kpi-meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
}
.kpi-badge {
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}
.badge-up { background: rgba(22, 163, 74, 0.12); color: #16a34a; }
.badge-down { background: rgba(220, 38, 38, 0.12); color: #dc2626; }
.badge-neutral { background: rgba(100, 116, 139, 0.12); color: #64748b; }
.kpi-prev {
    color: var(--muted, #6c757d);
}
.visitors-card {
    background: var(--surface, #fff);
    border: 1px solid var(--panel-border, rgba(0, 0, 0, 0.08));
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    overflow: hidden;
}
.visitors-card-header {
    padding: 0.85rem 1.15rem;
    border-bottom: 1px solid var(--panel-border, rgba(0, 0, 0, 0.06));
}
.visitors-card-body {
    padding: 1rem 1.15rem;
}
.analytics-table th, .sessions-table th {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
    color: var(--muted, #6c757d);
    padding: 0.65rem 0.85rem;
}
.analytics-table td, .sessions-table td {
    padding: 0.65rem 0.85rem;
    font-size: 0.86rem;
}
.device-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}
.legend-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    vertical-align: middle;
    margin-right: 3px;
}
.chart-legend-wrap {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--muted, #6c757d);
}
@media (max-width: 767px) {
    .visitors-header-bar { flex-direction: column; align-items: stretch; }
    .visitors-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .visitors-filter-card { flex-direction: column; align-items: stretch; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Custom Date Range Toggle
    var btnCustom = document.getElementById('btnToggleCustomDate');
    var formCustom = document.getElementById('customDateForm');
    if (btnCustom && formCustom) {
        btnCustom.addEventListener('click', function () {
            formCustom.classList.toggle('d-none');
        });
    }

    // Real-time Active Visitors Auto-refresh (Every 15s)
    setInterval(function () {
        var countEl = document.getElementById('activeVisitorsCount');
        if (!countEl) return;
        fetch('<?= BASE_URL ?>ajax/track_visitor.php?action=active_count', { method: 'GET' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && typeof data.active === 'number') {
                    countEl.textContent = data.active;
                }
            })
            .catch(function () {});
    }, 15000);

    // 1. Visitor Trend Timeline Chart
    var trendCtx = document.getElementById('visitorTrendChart');
    if (trendCtx && typeof Chart !== 'undefined') {
        var labels = <?= json_encode($timeline['labels']) ?>;
        var visitData = <?= json_encode($timeline['visits']) ?>;
        var pvData = <?= json_encode($timeline['pageviews']) ?>;

        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Visits',
                        data: visitData,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.08)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: labels.length > 30 ? 0 : 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Pageviews',
                        data: pvData,
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6, 182, 212, 0.04)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: labels.length > 30 ? 0 : 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        padding: 10,
                        boxPadding: 4
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12, font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { precision: 0, font: { size: 11 } }
                    }
                }
            }
        });
    }

    // 2. Traffic Sources Doughnut Chart
    var srcCtx = document.getElementById('trafficSourcesChart');
    if (srcCtx && typeof Chart !== 'undefined') {
        var srcLabels = <?= json_encode(array_column($trafficSources, 'label')) ?>;
        var srcCounts = <?= json_encode(array_column($trafficSources, 'count')) ?>;
        var srcColors = ['#4f46e5', '#3b82f6', '#06b6d4', '#ec4899', '#f59e0b', '#8b5cf6'];

        new Chart(srcCtx, {
            type: 'doughnut',
            data: {
                labels: srcLabels,
                datasets: [{
                    data: srcCounts,
                    backgroundColor: srcColors.slice(0, srcLabels.length),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: { padding: 8 }
                }
            }
        });
    }

    // 3. Device Types Doughnut Chart
    var devCtx = document.getElementById('deviceChart');
    if (devCtx && typeof Chart !== 'undefined') {
        var devCounts = [
            <?= (int)$deviceStats['desktop']['count'] ?>,
            <?= (int)$deviceStats['mobile']['count'] ?>,
            <?= (int)$deviceStats['tablet']['count'] ?>
        ];

        new Chart(devCtx, {
            type: 'doughnut',
            data: {
                labels: ['Desktop', 'Mobile', 'Tablet'],
                datasets: [{
                    data: devCounts,
                    backgroundColor: ['#4f46e5', '#10b981', '#06b6d4'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: { padding: 8 }
                }
            }
        });
    }
});
</script>
