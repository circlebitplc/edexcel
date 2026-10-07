<?php
declare(strict_types=1);

/**
 * public_html/admin/class_analytics.php
 *
 * Dedicated Admin Analytics & Conversion Report for /class.
 * Accessible exclusively to authorized administrators.
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../src/Services/ClassPageAnalyticsService.php';
require_once __DIR__ . '/../includes/helpers.php';

use Edexcel\Services\ClassPageAnalyticsService;

require_admin();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection is currently unavailable.");
}

ClassPageAnalyticsService::ensureSchema($pdo);

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
    case 'last_month':
        $from = (new DateTime('first day of last month'))->format('Y-m-d');
        $to = (new DateTime('last day of last month'))->format('Y-m-d');
        break;
    case '12m':
        $from = (new DateTime('-365 days'))->format('Y-m-d');
        $to = $todayStr;
        break;
    case 'custom':
        $from = (string)($_GET['from'] ?? (new DateTime('-6 days'))->format('Y-m-d'));
        $to = (string)($_GET['to'] ?? $todayStr);
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

// Handle CSV export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    ClassPageAnalyticsService::exportCsv($pdo, $from, $to);
    exit;
}

// Query analytics
$stats = ClassPageAnalyticsService::getDashboardStats($pdo, $from, $to);
$timeline = ClassPageAnalyticsService::getTimeline($pdo, $from, $to);
$monthlyTrend = ClassPageAnalyticsService::getMonthlyTrend($pdo);
$trafficSources = ClassPageAnalyticsService::getTrafficSources($pdo, $from, $to);
$deviceStats = ClassPageAnalyticsService::getDeviceBreakdown($pdo, $from, $to);
$techStats = ClassPageAnalyticsService::getBrowserAndOsStats($pdo, $from, $to);
$campaigns = ClassPageAnalyticsService::getCampaignStats($pdo, $from, $to);
$conversions = ClassPageAnalyticsService::getConversionBreakdown($pdo, $from, $to);
$recentActivity = ClassPageAnalyticsService::getRecentActivity($pdo, 25);
$locationStats = ClassPageAnalyticsService::getLocationStats($pdo, $from, $to, 6);

// Session Explorer filter & pagination
$sessPage = max(1, (int)($_GET['spage'] ?? 1));
$sessFilter = [
    'from' => $from,
    'to' => $to,
    'q' => trim((string)($_GET['sq'] ?? '')),
    'device_type' => trim((string)($_GET['sdevice'] ?? '')),
    'traffic_source' => trim((string)($_GET['ssource'] ?? '')),
];
$sessionsData = ClassPageAnalyticsService::getVisitorSessions($pdo, $sessFilter, $sessPage, 25);

$page_title = 'Class Page Analytics (/class) - Edexcel College';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4 class-analytics-dashboard">
    <!-- Header Controls & Date Filters -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 border-bottom pb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary px-2 py-1"><i class="bi bi-tag-fill me-1"></i> /class</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <span class="spinner-grow spinner-grow-sm me-1" role="status" aria-hidden="true" style="width: 8px; height: 8px;"></span> Live Tracking Active
                </span>
            </div>
            <h1 class="h3 mb-1 fw-bold text-dark">Class Page Analytics</h1>
            <p class="text-muted mb-0 small">
                Dedicated visitor dynamics, campaign performance, engagement telemetry, and conversions for <strong>https://edexcel.college/class</strong>.
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Date Filter Presets -->
            <div class="btn-group btn-group-sm" role="group" aria-label="Date presets">
                <a href="?vrange=today" class="btn btn-outline-secondary <?= $preset === 'today' ? 'active' : '' ?>">Today</a>
                <a href="?vrange=yesterday" class="btn btn-outline-secondary <?= $preset === 'yesterday' ? 'active' : '' ?>">Yesterday</a>
                <a href="?vrange=7d" class="btn btn-outline-secondary <?= $preset === '7d' ? 'active' : '' ?>">7 Days</a>
                <a href="?vrange=30d" class="btn btn-outline-secondary <?= $preset === '30d' ? 'active' : '' ?>">30 Days</a>
                <a href="?vrange=month" class="btn btn-outline-secondary <?= $preset === 'month' ? 'active' : '' ?>">This Month</a>
                <a href="?vrange=last_month" class="btn btn-outline-secondary <?= $preset === 'last_month' ? 'active' : '' ?>">Last Month</a>
                <a href="?vrange=12m" class="btn btn-outline-secondary <?= $preset === '12m' ? 'active' : '' ?>">12 Months</a>
            </div>

            <!-- Custom Date Range Form -->
            <form method="get" class="d-inline-flex align-items-center gap-1">
                <input type="hidden" name="vrange" value="custom">
                <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width: 130px;" aria-label="From Date">
                <span class="text-muted small">to</span>
                <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width: 130px;" aria-label="To Date">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-filter"></i> Apply</button>
            </form>

            <!-- CSV Export Button -->
            <a href="?action=export_csv&vrange=<?= urlencode($preset) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-sm btn-outline-success">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Views</span>
                    <i class="bi bi-eye text-primary fs-5"></i>
                </div>
                <div class="h3 fw-bold text-dark mb-1"><?= number_format($stats['total_views']) ?></div>
                <div class="small text-muted">Selected period</div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <a href="#visitor-explorer" class="card border-0 shadow-sm h-100 p-3 bg-white text-decoration-none text-reset transition-all hover-shadow" title="Click to view Unique Visitors &amp; session details below">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Unique Visitors</span>
                    <i class="bi bi-people text-info fs-5"></i>
                </div>
                <div class="h3 fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                    <span><?= number_format($stats['unique_visitors']) ?></span>
                    <span class="badge bg-info-subtle text-info border border-info-subtle small fw-normal" style="font-size: 0.7rem;"><i class="bi bi-arrow-down-circle"></i> View</span>
                </div>
                <div class="small text-muted">
                    <span class="text-dark fw-semibold"><?= number_format($stats['unique_ips']) ?></span> unique IPs · <?= number_format($stats['returning_visitors']) ?> ret.
                </div>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Views Today</span>
                    <i class="bi bi-calendar-event text-warning fs-5"></i>
                </div>
                <div class="h3 fw-bold text-dark mb-1"><?= number_format($stats['views_today']) ?></div>
                <div class="small text-muted">Yesterday: <?= number_format($stats['views_yesterday']) ?></div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">This Month</span>
                    <i class="bi bi-calendar-month text-secondary fs-5"></i>
                </div>
                <div class="h3 fw-bold text-dark mb-1"><?= number_format($stats['views_month']) ?></div>
                <div class="small text-muted">Year: <?= number_format($stats['views_year']) ?></div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 p-3 bg-white border-start border-success border-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">WhatsApp Clicks</span>
                    <i class="bi bi-whatsapp text-success fs-5"></i>
                </div>
                <div class="h3 fw-bold text-success mb-1"><?= number_format($stats['whatsapp_clicks']) ?></div>
                <div class="small text-muted">Class group joins</div>
            </div>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 p-3 bg-white border-start border-primary border-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Conversion Rate</span>
                    <i class="bi bi-lightning-charge text-primary fs-5"></i>
                </div>
                <div class="h3 fw-bold text-primary mb-1"><?= $stats['conversion_rate'] ?>%</div>
                <div class="small text-muted"><?= number_format($stats['registrations']) ?> forms submitted</div>
            </div>
        </div>
    </div>

    <!-- Main Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Daily Traffic Trend Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-0 text-dark"><i class="bi bi-graph-up text-primary me-2"></i> Daily Visitor &amp; View Dynamics</h2>
                        <small class="text-muted">Breakdown from <?= e($from) ?> to <?= e($to) ?></small>
                    </div>
                    <div class="d-flex align-items-center gap-3 small text-muted">
                        <span><span class="badge rounded-circle p-1 me-1" style="background:#4f46e5;"></span> Views</span>
                        <span><span class="badge rounded-circle p-1 me-1" style="background:#06b6d4;"></span> Unique</span>
                    </div>
                </div>
                <div style="position: relative; height: 260px;">
                    <canvas id="classTrafficChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 12-Month Traffic Trend -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-line text-info me-2"></i> 12-Month Trajectory</h2>
                        <small class="text-muted">Monthly unique visitors</small>
                    </div>
                </div>
                <div style="position: relative; height: 260px;">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Funnel & Conversion Section -->
    <div class="row g-4 mb-4">
        <!-- Registration & Conversion Funnel -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <h2 class="h6 fw-bold mb-1 text-dark"><i class="bi bi-funnel text-primary me-2"></i> Visitor Conversion Funnel</h2>
                <p class="text-muted small mb-3">Progression from initial landing to class selection and group join/registration.</p>

                <?php
                $f = $conversions['funnel'];
                $visits = max(1, (int)$f['step_visits']);
                $step1Pct = 100;
                $step2Pct = round(((int)$f['step_class_selection'] / $visits) * 100, 1);
                $step3Pct = round(((int)$f['step_wa_or_form_start'] / $visits) * 100, 1);
                $step4Pct = round(((int)$f['step_reg_submitted'] / $visits) * 100, 1);
                ?>

                <div class="funnel-container d-flex flex-column gap-3">
                    <!-- Step 1 -->
                    <div>
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span>1. Landed on /class</span>
                            <span><?= number_format((int)$f['step_visits']) ?> sessions (100%)</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div>
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span>2. Explored Classes / CTA Click</span>
                            <span><?= number_format((int)$f['step_class_selection']) ?> (<?= $step2Pct ?>%)</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?= min(100, $step2Pct) ?>%"></div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div>
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span>3. Joined WhatsApp or Started Form</span>
                            <span><?= number_format((int)$f['step_wa_or_form_start']) ?> (<?= $step3Pct ?>%)</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, $step3Pct) ?>%"></div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div>
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span>4. Form Registration Completed</span>
                            <span><?= number_format((int)$f['step_reg_submitted']) ?> (<?= $step4Pct ?>%)</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, $step4Pct) ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-light rounded text-muted small">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    <strong>Conversion Metric:</strong> Students who join a WhatsApp group are counted as direct intent conversions even if they choose not to fill out the optional pre-enrolment intake form.
                </div>
            </div>
        </div>

        <!-- WhatsApp Clicks By Class -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 fw-bold mb-0 text-dark"><i class="bi bi-whatsapp text-success me-2"></i> WhatsApp Group Joins by Class</h2>
                    <span class="badge bg-success-subtle text-success"><?= number_format($stats['whatsapp_clicks']) ?> Total</span>
                </div>
                <p class="text-muted small mb-3">Specific class group targets chosen by students.</p>

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Class &amp; Format Target</th>
                                <th class="text-end">Clicks</th>
                                <th class="text-end">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($conversions['whatsapp_by_class'])): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No WhatsApp clicks recorded in this period yet.</td>
                                </tr>
                            <?php else: 
                                $totalWa = max(1, array_sum(array_column($conversions['whatsapp_by_class'], 'count')));
                                foreach ($conversions['whatsapp_by_class'] as $row): 
                                    $cnt = (int)$row['count'];
                                    $pct = round(($cnt / $totalWa) * 100, 1);
                                    $targetLabel = ucwords(str_replace('_', ' ', (string)$row['event_target']));
                            ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-arrow-right-short text-success me-1"></i>
                                        <strong><?= e($targetLabel) ?></strong>
                                    </td>
                                    <td class="text-end fw-bold"><?= number_format($cnt) ?></td>
                                    <td class="text-end text-muted"><?= $pct ?>%</td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Engagement Breakdown: Videos & PDF -->
    <div class="row g-4 mb-4">
        <!-- Video Engagement -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 fw-bold mb-0 text-dark"><i class="bi bi-play-circle text-danger me-2"></i> Video Engagement Telemetry</h2>
                    <span class="badge bg-danger-subtle text-danger"><?= number_format($stats['video_plays']) ?> Total Plays</span>
                </div>
                <p class="text-muted small mb-3">Bunny.net promotional classroom video interactions.</p>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Video Title</th>
                                <th class="text-end">Interactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($conversions['video_engagement'])): ?>
                                <tr>
                                    <td colspan="2" class="text-center py-3 text-muted">No video interaction events recorded yet.</td>
                                </tr>
                            <?php else: 
                                foreach ($conversions['video_engagement'] as $row): 
                                    $vidLabel = ucwords(str_replace('_', ' ', (string)$row['event_target']));
                            ?>
                                <tr>
                                    <td><i class="bi bi-film me-2 text-danger"></i> <?= e($vidLabel) ?></td>
                                    <td class="text-end fw-bold"><?= number_format((int)$row['count']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PDF Engagement -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-pdf text-warning me-2"></i> PDF Mock Paper Preview Telemetry</h2>
                    <span class="badge bg-warning-subtle text-warning-emphasis"><?= number_format($stats['pdf_opens']) ?> Opens</span>
                </div>
                <p class="text-muted small mb-3">Pages 1–5 inspected by prospective students.</p>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Preview Interaction</th>
                                <th class="text-end">Events</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($conversions['pdf_engagement'])): ?>
                                <tr>
                                    <td colspan="2" class="text-center py-3 text-muted">No PDF interaction events recorded yet.</td>
                                </tr>
                            <?php else: 
                                foreach ($conversions['pdf_engagement'] as $row): 
                                    $pdfLabel = ucwords(str_replace('_', ' ', (string)$row['event_target']));
                            ?>
                                <tr>
                                    <td><i class="bi bi-file-earmark-text me-2 text-primary"></i> <?= e($pdfLabel) ?></td>
                                    <td class="text-end fw-bold"><?= number_format((int)$row['count']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Traffic Sources, Devices & Geography Row -->
    <div class="row g-4 mb-4">
        <!-- Traffic Sources -->
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <h2 class="h6 fw-bold mb-1 text-dark"><i class="bi bi-signpost-split text-primary me-2"></i> Traffic Sources</h2>
                <p class="text-muted small mb-3">Where visitors come from</p>
                <div style="position: relative; height: 160px;" class="mb-3">
                    <canvas id="sourcesChart"></canvas>
                </div>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($trafficSources as $s): ?>
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span><?= e($s['label']) ?></span>
                            <span class="fw-bold"><?= number_format($s['count']) ?> (<?= $s['percentage'] ?>%)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Devices -->
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <h2 class="h6 fw-bold mb-1 text-dark"><i class="bi bi-phone text-info me-2"></i> Device Category</h2>
                <p class="text-muted small mb-3">Mobile vs Desktop audience</p>
                <div style="position: relative; height: 160px;" class="mb-3">
                    <canvas id="devicesChart"></canvas>
                </div>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($deviceStats as $d): ?>
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span><?= e($d['label']) ?></span>
                            <span class="fw-bold"><?= number_format($d['count']) ?> (<?= $d['percentage'] ?>%)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Browsers & OS -->
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <h2 class="h6 fw-bold mb-1 text-dark"><i class="bi bi-laptop text-secondary me-2"></i> Technology Profile</h2>
                <p class="text-muted small mb-3">Top operating systems &amp; browsers</p>

                <div class="mb-3">
                    <span class="fw-bold small text-uppercase text-muted">Top Browsers</span>
                    <ul class="list-unstyled mb-0 small mt-1">
                        <?php if (empty($techStats['browsers'])): ?>
                            <li class="text-muted py-1">No browser data yet</li>
                        <?php else: foreach (array_slice($techStats['browsers'], 0, 4) as $b): ?>
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span><?= e((string)($b['label'] ?? $b['browser'] ?? 'Unknown')) ?></span>
                                <span class="fw-semibold"><?= number_format((int)$b['count']) ?></span>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>

                <div>
                    <span class="fw-bold small text-uppercase text-muted">Operating Systems</span>
                    <ul class="list-unstyled mb-0 small mt-1">
                        <?php if (empty($techStats['os'])): ?>
                            <li class="text-muted py-1">No OS data yet</li>
                        <?php else: foreach (array_slice($techStats['os'], 0, 4) as $o): ?>
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span><?= e((string)($o['label'] ?? $o['os'] ?? 'Unknown')) ?></span>
                                <span class="fw-semibold"><?= number_format((int)$o['count']) ?></span>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Visitor Geography -->
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm p-4 bg-white h-100">
                <h2 class="h6 fw-bold mb-1 text-dark"><i class="bi bi-geo-alt text-danger me-2"></i> Visitor Geography</h2>
                <p class="text-muted small mb-3">Country &amp; region breakdown</p>

                <ul class="list-unstyled mb-0 small">
                    <?php if (empty($locationStats)): ?>
                        <li class="text-muted py-3 text-center">No location records yet</li>
                    <?php else: foreach ($locationStats as $loc): ?>
                        <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <span class="me-1 fs-6"><?= e($loc['flag']) ?></span>
                                <span class="fw-semibold text-dark"><?= e($loc['country_name'] ?? $loc['name'] ?? 'Unknown') ?></span>
                            </div>
                            <span class="badge bg-light text-dark border fw-bold"><?= number_format((int)($loc['sessions'] ?? $loc['count'] ?? 0)) ?> sessions</span>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Unique Visitors & Detailed Session Explorer -->
    <div class="card border-0 shadow-sm p-4 bg-white mb-4" id="visitor-explorer">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-0 text-dark"><i class="bi bi-people-fill text-primary me-2"></i> Unique Visitors &amp; Session Explorer</h2>
                <small class="text-muted">Full visitor intelligence: Real IP address, geolocation, exact device, OS, browser, dwell time, and action history.</small>
            </div>
            <div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-semibold">
                    <?= number_format($sessionsData['total']) ?> Total Visitor Sessions Recorded
                </span>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="get" class="row g-2 mb-3 align-items-center bg-light p-3 rounded">
            <input type="hidden" name="vrange" value="<?= e($preset) ?>">
            <input type="hidden" name="from" value="<?= e($from) ?>">
            <input type="hidden" name="to" value="<?= e($to) ?>">

            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="sq" value="<?= e($sessFilter['q']) ?>" class="form-control" placeholder="Search IP, Visitor ID, City, OS, Browser...">
                </div>
            </div>

            <div class="col-6 col-md-3">
                <select name="sdevice" class="form-select form-select-sm">
                    <option value="">All Devices</option>
                    <option value="mobile" <?= $sessFilter['device_type'] === 'mobile' ? 'selected' : '' ?>>Mobile</option>
                    <option value="desktop" <?= $sessFilter['device_type'] === 'desktop' ? 'selected' : '' ?>>Desktop</option>
                    <option value="tablet" <?= $sessFilter['device_type'] === 'tablet' ? 'selected' : '' ?>>Tablet</option>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <select name="ssource" class="form-select form-select-sm">
                    <option value="">All Traffic Sources</option>
                    <option value="direct" <?= $sessFilter['traffic_source'] === 'direct' ? 'selected' : '' ?>>Direct</option>
                    <option value="whatsapp" <?= $sessFilter['traffic_source'] === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                    <option value="facebook" <?= $sessFilter['traffic_source'] === 'facebook' ? 'selected' : '' ?>>Facebook</option>
                    <option value="instagram" <?= $sessFilter['traffic_source'] === 'instagram' ? 'selected' : '' ?>>Instagram</option>
                    <option value="google" <?= $sessFilter['traffic_source'] === 'google' ? 'selected' : '' ?>>Google Search</option>
                    <option value="tiktok" <?= $sessFilter['traffic_source'] === 'tiktok' ? 'selected' : '' ?>>TikTok</option>
                    <option value="referral" <?= $sessFilter['traffic_source'] === 'referral' ? 'selected' : '' ?>>Other Referral</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
                <?php if ($sessFilter['q'] !== '' || $sessFilter['device_type'] !== '' || $sessFilter['traffic_source'] !== ''): ?>
                    <a href="?vrange=<?= urlencode($preset) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Sessions Table -->
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.875rem;" id="visitorSessionsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 190px;">Unique Visitor / ID</th>
                        <th style="width: 190px;">IP Address &amp; Location</th>
                        <th style="width: 210px;">Device &amp; Environment</th>
                        <th style="width: 140px;">Traffic Channel</th>
                        <th style="width: 110px;" class="text-center">Views / Duration</th>
                        <th>Actions &amp; Conversions</th>
                        <th style="width: 70px;" class="text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessionsData['items'])): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                                No visitor sessions found matching the selected range or filters.
                            </td>
                        </tr>
                    <?php else: 
                        foreach ($sessionsData['items'] as $sess): 
                            $rawIp = (string)($sess['ip_address'] ?? 'Unknown');
                            $ipDisplay = ($rawIp !== '' && $rawIp !== 'Unknown') ? $rawIp : 'Private/Hidden';
                            $lookupUrl = ($rawIp !== '' && $rawIp !== 'Unknown') ? 'https://ipinfo.io/' . urlencode($rawIp) : '#';
                            $devType = (string)($sess['device_type'] ?? 'desktop');
                            $deviceIcon = match($devType) {
                                'mobile' => 'bi-phone',
                                'tablet' => 'bi-tablet',
                                default => 'bi-laptop'
                            };
                            $jsonPayload = htmlspecialchars(json_encode([
                                'visitor_id' => $sess['visitor_id'] ?? ($sess['visitor_hash'] ?? 'Unknown'),
                                'short_id' => $sess['short_visitor_id'] ?? substr((string)($sess['visitor_hash'] ?? 'Unknown'), 0, 8),
                                'is_new' => !empty($sess['is_new']),
                                'first_seen' => $sess['first_seen_formatted'] ?? ($sess['visit_date'] ?? 'N/A'),
                                'ip_address' => $rawIp,
                                'ip_display' => $ipDisplay,
                                'country_name' => $sess['country_name'] ?? 'Unknown',
                                'country_flag' => $sess['country_flag'] ?? '',
                                'city' => $sess['city'] ?? '',
                                'device_type' => $devType,
                                'device_icon' => $deviceIcon,
                                'os' => $sess['os'] ?? 'Unknown',
                                'browser' => $sess['browser'] ?? 'Unknown',
                                'user_agent' => $sess['user_agent'] ?? '',
                                'traffic_source' => $sess['traffic_source'] ?? 'direct',
                                'referrer_host' => $sess['referrer_host'] ?? '',
                                'campaign' => $sess['campaign'] ?? '',
                                'landing_url' => $sess['landing_url'] ?? '/class',
                                'pageviews' => (int)($sess['pageviews'] ?? ($sess['pageviews_count'] ?? 1)),
                                'duration' => $sess['dwell_time_formatted'] ?? '0s',
                                'actions' => $sess['actions'] ?? [],
                            ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="visitor-row cursor-pointer" data-visitor='<?= $jsonPayload ?>' style="cursor: pointer;">
                            <!-- 1. Visitor & Time -->
                            <td>
                                <button type="button" class="btn btn-link p-0 text-start text-decoration-none fw-semibold text-primary btn-inspect-visitor d-block" data-visitor='<?= $jsonPayload ?>'>
                                    <i class="bi bi-person-circle me-1"></i>
                                    <span><?= e($sess['short_visitor_id'] ?? substr((string)($sess['visitor_hash'] ?? ''), 0, 8)) ?></span>
                                    <?php if (!empty($sess['is_new'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem;">NEW</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size: 0.65rem;">RETURNING</span>
                                    <?php endif; ?>
                                </button>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-clock me-1"></i><?= e($sess['first_seen_formatted'] ?? '') ?>
                                </small>
                            </td>

                            <!-- 2. IP & Location -->
                            <td>
                                <div class="fw-bold font-monospace">
                                    <?php if ($sess['ip_address'] !== 'Unknown'): ?>
                                        <a href="<?= e($lookupUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-dark" title="Lookup IP details on ipinfo.io" onclick="event.stopPropagation();">
                                            <?= e($ipDisplay) ?> <i class="bi bi-box-arrow-up-right text-muted" style="font-size: 0.75rem;"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><?= e($ipDisplay) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted mt-1">
                                    <span class="me-1"><?= e($sess['country_flag']) ?></span>
                                    <span><?= e($sess['country_name']) ?></span>
                                    <?php if ($sess['city'] && $sess['city'] !== 'Unknown'): ?>
                                        · <span class="fw-semibold"><?= e($sess['city']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- 3. Device & Environment -->
                            <td>
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <span class="badge bg-primary-subtle text-primary text-capitalize">
                                        <i class="bi <?= $deviceIcon ?> me-1"></i><?= e($sess['device_type']) ?>
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        <?= e($sess['browser']) ?>
                                    </span>
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        <?= e($sess['os']) ?>
                                    </span>
                                </div>
                                <?php if ($sess['user_agent']): ?>
                                    <div class="text-truncate text-muted small" style="max-width: 200px; font-size: 0.75rem;" title="<?= e($sess['user_agent']) ?>">
                                        UA: <?= e($sess['user_agent']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- 4. Channel & Campaign -->
                            <td>
                                <div>
                                    <span class="badge bg-dark-subtle text-dark text-capitalize">
                                        <?= e($sess['traffic_source']) ?>
                                    </span>
                                </div>
                                <?php if ($sess['campaign']): ?>
                                    <small class="badge bg-warning-subtle text-dark border d-inline-block mt-1">
                                        <i class="bi bi-tag-fill me-1"></i><?= e($sess['campaign']) ?>
                                    </small>
                                <?php elseif ($sess['referrer_host']): ?>
                                    <small class="text-muted d-block mt-1 text-truncate" style="max-width: 130px;" title="<?= e($sess['referrer_host']) ?>">
                                        ref: <?= e($sess['referrer_host']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <!-- 5. Views / Duration -->
                            <td class="text-center">
                                <div class="fw-bold text-dark"><?= number_format($sess['pageviews']) ?> <small class="text-muted fw-normal">views</small></div>
                                <small class="text-muted d-block mt-1 font-monospace">
                                    <i class="bi bi-stopwatch me-1"></i><?= e($sess['dwell_time_formatted']) ?>
                                </small>
                            </td>

                            <!-- 6. Actions Performed -->
                            <td>
                                <?php if (empty($sess['actions'])): ?>
                                    <span class="text-muted small">Browsed landing page</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($sess['actions'] as $act): 
                                            $actName = (string)($act['name'] ?? '');
                                            $actTarget = (string)($act['target'] ?? '');
                                            $badgeClass = 'bg-light text-dark border';
                                            $icon = 'bi-circle';
                                            $label = $actName;

                                            if (str_contains($actName, 'whatsapp') || $actName === 'whatsapp_click') {
                                                $badgeClass = 'bg-success text-white';
                                                $icon = 'bi-whatsapp';
                                                $label = 'WhatsApp: ' . ucwords(str_replace('_', ' ', $actTarget));
                                            } elseif (str_contains($actName, 'reg') || $actName === 'form_submitted') {
                                                $badgeClass = 'bg-primary text-white';
                                                $icon = 'bi-check2-circle';
                                                $label = 'Registered Form';
                                            } elseif (str_contains($actName, 'pdf')) {
                                                $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning';
                                                $icon = 'bi-file-earmark-pdf';
                                                $label = 'PDF Preview';
                                            } elseif (str_contains($actName, 'video')) {
                                                $badgeClass = 'bg-danger text-white';
                                                $icon = 'bi-play-fill';
                                                $label = 'Played Video ' . ucwords(str_replace('_', ' ', $actTarget));
                                            }
                                        ?>
                                            <span class="badge <?= $badgeClass ?> d-inline-flex align-items-center gap-1" style="font-size: 0.72rem; padding: 4px 6px;">
                                                <i class="bi <?= $icon ?>"></i> <?= e($label) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- 7. View Full Details Button -->
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 btn-inspect-visitor" data-visitor='<?= $jsonPayload ?>' title="Click to view full visitor profile, IP details, and complete activity history">
                                    <i class="bi bi-box-arrow-in-right"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($sessionsData['pages'] > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                <small class="text-muted">
                    Showing page <strong><?= $sessionsData['page'] ?></strong> of <strong><?= $sessionsData['pages'] ?></strong> (<?= number_format($sessionsData['total']) ?> sessions)
                </small>
                <div class="btn-group btn-group-sm" role="group">
                    <?php if ($sessionsData['page'] > 1): ?>
                        <a href="?vrange=<?= urlencode($preset) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&sq=<?= urlencode($sessFilter['q']) ?>&sdevice=<?= urlencode($sessFilter['device_type']) ?>&ssource=<?= urlencode($sessFilter['traffic_source']) ?>&spage=<?= $sessionsData['page'] - 1 ?>#visitor-explorer" class="btn btn-outline-secondary">
                            &laquo; Previous
                        </a>
                    <?php endif; ?>

                    <span class="btn btn-outline-secondary disabled">
                        Page <?= $sessionsData['page'] ?> / <?= $sessionsData['pages'] ?>
                    </span>

                    <?php if ($sessionsData['page'] < $sessionsData['pages']): ?>
                        <a href="?vrange=<?= urlencode($preset) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&sq=<?= urlencode($sessFilter['q']) ?>&sdevice=<?= urlencode($sessFilter['device_type']) ?>&ssource=<?= urlencode($sessFilter['traffic_source']) ?>&spage=<?= $sessionsData['page'] + 1 ?>#visitor-explorer" class="btn btn-outline-secondary">
                            Next &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal: Complete Visitor Intelligence & Activity Chronology -->
    <div class="modal fade" id="visitorDetailModal" tabindex="-1" aria-labelledby="visitorDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-badge fs-4 text-info"></i>
                        <div>
                            <h5 class="modal-title mb-0 fw-bold" id="visitorDetailModalLabel">Visitor Intelligence Profile</h5>
                            <small class="text-white-50" id="modalVisitorSubtitle">Detailed audit trail and live telemetry</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <!-- Key Badges Header -->
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3 p-3 bg-white rounded shadow-sm border">
                        <div class="me-auto">
                            <span class="text-muted small text-uppercase fw-semibold d-block">Visitor Hash ID</span>
                            <span class="font-monospace fw-bold text-dark fs-6 user-select-all" id="modalVisitorId">-</span>
                        </div>
                        <div id="modalVisitorStatusBadge"></div>
                    </div>

                    <!-- Details Grid -->
                    <div class="row g-3 mb-4">
                        <!-- IP & Geolocation -->
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                                <h6 class="fw-bold text-dark mb-2 border-bottom pb-2">
                                    <i class="bi bi-geo-alt-fill text-danger me-2"></i> IP Address &amp; Geolocation
                                </h6>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Public IP Address:</small>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="fw-bold font-monospace fs-6 text-dark" id="modalIpAddress">-</span>
                                        <a href="#" target="_blank" rel="noopener noreferrer" id="modalIpLookupLink" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Check IP (ipinfo.io)
                                        </a>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Country &amp; Region:</small>
                                    <span class="fw-semibold text-dark fs-6" id="modalCountry">-</span>
                                </div>
                                <div>
                                    <small class="text-muted d-block">City Location:</small>
                                    <span class="fw-semibold text-dark" id="modalCity">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Device & Environment -->
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                                <h6 class="fw-bold text-dark mb-2 border-bottom pb-2">
                                    <i class="bi bi-laptop text-primary me-2"></i> Device &amp; Environment
                                </h6>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Device Category:</small>
                                    <span class="badge bg-primary-subtle text-primary text-capitalize fs-6 mt-1" id="modalDeviceBadge">-</span>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Operating System:</small>
                                        <span class="fw-bold text-dark" id="modalOs">-</span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Web Browser:</small>
                                        <span class="fw-bold text-dark" id="modalBrowser">-</span>
                                    </div>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Raw User-Agent:</small>
                                    <code class="d-block small p-2 bg-light rounded text-muted mt-1 user-select-all" style="font-size: 0.7rem; max-height: 60px; overflow-y: auto;" id="modalUserAgent">-</code>
                                </div>
                            </div>
                        </div>

                        <!-- Engagement & Traffic Channel -->
                        <div class="col-12">
                            <div class="card border-0 shadow-sm p-3 bg-white">
                                <h6 class="fw-bold text-dark mb-2 border-bottom pb-2">
                                    <i class="bi bi-compass text-info me-2"></i> Traffic Channel &amp; Session Metrics
                                </h6>
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Traffic Source:</small>
                                        <span class="badge bg-dark-subtle text-dark text-capitalize fs-6 mt-1" id="modalSource">-</span>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Marketing Campaign:</small>
                                        <span class="fw-semibold text-dark" id="modalCampaign">-</span>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Total Pageviews:</small>
                                        <span class="fw-bold text-primary fs-6" id="modalPageviews">-</span>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Time Spent on Page:</small>
                                        <span class="fw-bold font-monospace text-success fs-6" id="modalDwellTime">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Full Chronology of Activities -->
                    <div class="card border-0 shadow-sm p-3 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-clock-history text-warning me-2"></i> Complete Activities &amp; Conversion Trail
                            </h6>
                            <span class="badge bg-secondary-subtle text-secondary" id="modalActionsCount">0 actions</span>
                        </div>

                        <div id="modalActivitiesList" class="timeline-activity">
                            <!-- Injected dynamically via JS -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close Profile</button>
                </div>
            </div>
        </div>
    </div>

    <!-- UTM Campaigns Table -->
    <div class="card border-0 shadow-sm p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h6 fw-bold mb-0 text-dark"><i class="bi bi-megaphone text-primary me-2"></i> Campaign / UTM Tracking</h2>
                <small class="text-muted">Performance of targeted promotional marketing campaigns</small>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Campaign</th>
                        <th>Source</th>
                        <th>Medium</th>
                        <th class="text-end">Visits</th>
                        <th class="text-end">Unique</th>
                        <th class="text-end">Conversions</th>
                        <th class="text-end">Conversion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                No UTM tagged traffic recorded yet. Promote with links like:<br>
                                <code>https://edexcel.college/class?utm_source=facebook&amp;utm_medium=social&amp;utm_campaign=mock2027</code>
                            </td>
                        </tr>
                    <?php else: 
                        foreach ($campaigns as $c): ?>
                            <tr>
                                <td class="fw-bold"><?= e((string)$c['campaign']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e((string)$c['source']) ?></span></td>
                                <td><?= e((string)$c['medium']) ?></td>
                                <td class="text-end"><?= number_format((int)$c['visits']) ?></td>
                                <td class="text-end"><?= number_format((int)$c['unique_visitors']) ?></td>
                                <td class="text-end fw-bold text-success"><?= number_format((int)$c['conversions']) ?></td>
                                <td class="text-end fw-bold"><?= $c['conversion_rate'] ?>%</td>
                            </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Real-Time / Recent Activity Feed -->
    <div class="card border-0 shadow-sm p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h6 fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-primary me-2"></i> Recent / Real-Time Activity</h2>
                <small class="text-muted">Live event stream with device and channel telemetry</small>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 140px;">Timestamp</th>
                        <th>Action Performed</th>
                        <th>Device</th>
                        <th>Channel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentActivity)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No recent activity recorded yet.</td>
                        </tr>
                    <?php else: 
                        foreach ($recentActivity as $act): ?>
                            <tr>
                                <td class="text-muted small">
                                    <i class="bi bi-clock me-1"></i> <?= e($act['date']) ?>, <?= e($act['time']) ?>
                                </td>
                                <td class="fw-semibold text-dark">
                                    <?= e($act['desc']) ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($act['device']) ?></span></td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= e($act['source']) ?></span></td>
                            </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Chart.js Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Daily Traffic Chart
    var trafficCtx = document.getElementById('classTrafficChart');
    if (trafficCtx && typeof Chart !== 'undefined') {
        new Chart(trafficCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($timeline['labels']) ?>,
                datasets: [
                    {
                        label: 'Pageviews',
                        data: <?= json_encode($timeline['views']) ?>,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3
                    },
                    {
                        label: 'Unique Visitors',
                        data: <?= json_encode($timeline['visitors']) ?>,
                        borderColor: '#06b6d4',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.35,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. 12-Month Bar Chart
    var monthlyCtx = document.getElementById('monthlyTrendChart');
    if (monthlyCtx && typeof Chart !== 'undefined') {
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthlyTrend['labels']) ?>,
                datasets: [
                    {
                        label: 'Unique Visitors',
                        data: <?= json_encode($monthlyTrend['visitors']) ?>,
                        backgroundColor: '#0ea5e9',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 3. Traffic Sources Chart
    var sourcesCtx = document.getElementById('sourcesChart');
    if (sourcesCtx && typeof Chart !== 'undefined') {
        var srcLabels = <?= json_encode(array_column($trafficSources, 'label')) ?>;
        var srcData = <?= json_encode(array_column($trafficSources, 'count')) ?>;
        new Chart(sourcesCtx, {
            type: 'doughnut',
            data: {
                labels: srcLabels,
                datasets: [{
                    data: srcData,
                    backgroundColor: ['#4f46e5', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                cutout: '70%'
            }
        });
    }

    // 4. Device Breakdown Chart
    var devicesCtx = document.getElementById('devicesChart');
    if (devicesCtx && typeof Chart !== 'undefined') {
        var devLabels = <?= json_encode(array_column($deviceStats, 'label')) ?>;
        var devData = <?= json_encode(array_column($deviceStats, 'count')) ?>;
        new Chart(devicesCtx, {
            type: 'doughnut',
            data: {
                labels: devLabels,
                datasets: [{
                    data: devData,
                    backgroundColor: ['#2563eb', '#10b981', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                cutout: '70%'
            }
        });
    }

    // 5. Visitor Intelligence Modal Interaction
    var visitorModalEl = document.getElementById('visitorDetailModal');
    var visitorModal = (visitorModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(visitorModalEl) : null;

    function openVisitorProfile(data) {
        if (!data || !visitorModalEl) return;

        // Visitor IDs & badges
        document.getElementById('modalVisitorId').textContent = data.visitor_id || 'Unknown';
        document.getElementById('modalVisitorSubtitle').textContent = 'First seen: ' + (data.first_seen || 'N/A');

        var statusBadge = document.getElementById('modalVisitorStatusBadge');
        if (data.is_new) {
            statusBadge.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-star-fill me-1"></i> First-time Visitor</span>';
        } else {
            statusBadge.innerHTML = '<span class="badge bg-secondary-subtle text-secondary px-2 py-1"><i class="bi bi-arrow-repeat me-1"></i> Returning Visitor</span>';
        }

        // IP & Geo
        var ipEl = document.getElementById('modalIpAddress');
        var ipLink = document.getElementById('modalIpLookupLink');
        ipEl.textContent = data.ip_display || data.ip_address || 'Private/Hidden';
        if (data.ip_address && data.ip_address !== 'Unknown' && data.ip_address !== 'Private/Hidden') {
            ipLink.href = 'https://ipinfo.io/' + encodeURIComponent(data.ip_address);
            ipLink.style.display = 'inline-block';
        } else {
            ipLink.style.display = 'none';
        }

        document.getElementById('modalCountry').innerHTML = (data.country_flag ? '<span class="me-2 fs-5">' + data.country_flag + '</span>' : '') + (data.country_name || 'Unknown');
        document.getElementById('modalCity').textContent = data.city ? data.city : 'Not available';

        // Device
        document.getElementById('modalDeviceBadge').innerHTML = '<i class="bi ' + (data.device_icon || 'bi-phone') + ' me-1"></i> ' + (data.device_type || 'Desktop');
        document.getElementById('modalOs').textContent = data.os || 'Unknown';
        document.getElementById('modalBrowser').textContent = data.browser || 'Unknown';
        document.getElementById('modalUserAgent').textContent = data.user_agent || 'Not captured';

        // Traffic & Engagement
        document.getElementById('modalSource').textContent = data.traffic_source || 'direct';
        document.getElementById('modalCampaign').textContent = data.campaign ? data.campaign : (data.referrer_host ? 'Referrer: ' + data.referrer_host : 'None (Direct)');
        document.getElementById('modalPageviews').textContent = (data.pageviews || 1) + ' views';
        document.getElementById('modalDwellTime').textContent = data.duration || '0s';

        // Activities Chronology List
        var actContainer = document.getElementById('modalActivitiesList');
        var actions = Array.isArray(data.actions) ? data.actions : [];
        document.getElementById('modalActionsCount').textContent = actions.length + (actions.length === 1 ? ' action recorded' : ' actions recorded');

        if (actions.length === 0) {
            actContainer.innerHTML = '<div class="p-3 text-center text-muted small"><i class="bi bi-info-circle me-1"></i> Visitor browsed the landing page sections without triggering specific CTA buttons or preview downloads.</div>';
        } else {
            var html = '<div class="list-group list-group-flush">';
            actions.forEach(function (act, idx) {
                var actName = act.name || '';
                var target = act.target || '';
                var label = act.label || actName;
                var time = act.time ? '<span class="text-muted small ms-auto font-monospace"><i class="bi bi-clock me-1"></i>' + act.time + '</span>' : '';
                var icon = 'bi-circle';
                var badgeBg = 'bg-light text-dark border';

                if (actName.indexOf('whatsapp') !== -1) {
                    icon = 'bi-whatsapp';
                    badgeBg = 'bg-success text-white';
                } else if (actName.indexOf('reg') !== -1) {
                    icon = 'bi-check2-circle';
                    badgeBg = 'bg-primary text-white';
                } else if (actName.indexOf('pdf') !== -1) {
                    icon = 'bi-file-earmark-pdf';
                    badgeBg = 'bg-warning-subtle text-warning-emphasis border border-warning';
                } else if (actName.indexOf('video') !== -1) {
                    icon = 'bi-play-fill';
                    badgeBg = 'bg-danger text-white';
                }

                html += '<div class="list-group-item d-flex align-items-center justify-content-between px-0 py-2">' +
                        '  <div class="d-flex align-items-center gap-2">' +
                        '    <span class="badge rounded-circle p-2 ' + badgeBg + '"><i class="bi ' + icon + '"></i></span>' +
                        '    <div>' +
                        '      <strong class="d-block text-dark small">' + label + '</strong>' +
                        (target ? '      <span class="text-muted" style="font-size: 0.75rem;">Target: ' + target.replace(/_/g, ' ') + '</span>' : '') +
                        '    </div>' +
                        '  </div>' +
                        time +
                        '</div>';
            });
            html += '</div>';
            actContainer.innerHTML = html;
        }

        if (visitorModal) {
            visitorModal.show();
        }
    }

    // Attach click listeners to rows and buttons
    document.querySelectorAll('.btn-inspect-visitor').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            try {
                var d = JSON.parse(btn.getAttribute('data-visitor'));
                openVisitorProfile(d);
            } catch (err) {
                console.error('Failed to parse visitor data:', err);
            }
        });
    });

    document.querySelectorAll('.visitor-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // Prevent if user clicked an anchor link or button inside the row
            if (e.target.closest('a') || e.target.closest('button')) return;
            try {
                var d = JSON.parse(row.getAttribute('data-visitor'));
                openVisitorProfile(d);
            } catch (err) {
                console.error('Failed to parse row visitor data:', err);
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
