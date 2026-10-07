<?php
declare(strict_types=1);

/**
 * export_visitor_analytics.php
 * Export website visitor analytics as CSV or PDF.
 * Restricted to administrators only.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/college_contact.php';
require_once __DIR__ . '/../src/Services/SiteVisitorAnalyticsService.php';

use Edexcel\Services\SiteVisitorAnalyticsService;

require_admin();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database unavailable.');
}

$type = strtolower((string)($_GET['type'] ?? 'csv'));
$fromDate = (string)($_GET['from'] ?? date('Y-m-d', strtotime('-6 days')));
$toDate = (string)($_GET['to'] ?? date('Y-m-d'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = date('Y-m-d', strtotime('-6 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = date('Y-m-d');
}
if ($fromDate > $toDate) {
    $temp = $fromDate;
    $fromDate = $toDate;
    $toDate = $temp;
}

$stats = SiteVisitorAnalyticsService::getDashboardStats($pdo, $fromDate, $toDate);
$topPages = SiteVisitorAnalyticsService::getTopPages($pdo, $fromDate, $toDate, 25);
$trafficSources = SiteVisitorAnalyticsService::getTrafficSources($pdo, $fromDate, $toDate);
$deviceStats = SiteVisitorAnalyticsService::getDeviceBreakdown($pdo, $fromDate, $toDate);
$browserOs = SiteVisitorAnalyticsService::getBrowserAndOsStats($pdo, $fromDate, $toDate, 10);
$locationStats = SiteVisitorAnalyticsService::getLocationStats($pdo, $fromDate, $toDate, 20);
$sessionsData = SiteVisitorAnalyticsService::getSessions($pdo, ['from' => $fromDate, 'to' => $toDate], 1, 500);

$college = college_contact($pdo);
$brandName = (string)($college['name'] ?? 'Edexcel College');

// Helper to escape formula injection in CSV
function csv_cell_safe(mixed $val): string
{
    $str = (string)$val;
    if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $str;
    }
    return $str;
}

// -------------------------------------------------------------
// 1. CSV EXPORT
// -------------------------------------------------------------
if ($type === 'csv') {
    $filename = 'visitor_analytics_' . $fromDate . '_to_' . $toDate . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit('Could not generate CSV.');
    }

    // UTF-8 BOM for Excel
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header Meta
    fputcsv($out, [$brandName . ' - Site Visitors Analytics Report']);
    fputcsv($out, ['Report Date Range', $fromDate . ' to ' . $toDate . ' (' . $stats['days'] . ' days)']);
    fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($out, []);

    // 1. Overview KPIs
    fputcsv($out, ['=== EXECUTIVE SUMMARY KPIS ===']);
    fputcsv($out, ['Metric', 'Current Period', 'Previous Period', 'Change %']);
    fputcsv($out, [
        'Total Visits',
        $stats['current']['visits'],
        $stats['previous']['visits'],
        $stats['change']['visits'] . '%'
    ]);
    fputcsv($out, [
        'Unique Visitors',
        $stats['current']['unique_visitors'],
        $stats['previous']['unique_visitors'],
        $stats['change']['unique_visitors'] . '%'
    ]);
    fputcsv($out, [
        'Total Pageviews',
        $stats['current']['pageviews'],
        $stats['previous']['pageviews'],
        $stats['change']['pageviews'] . '%'
    ]);
    fputcsv($out, [
        'Avg Session Duration',
        SiteVisitorAnalyticsService::formatDuration($stats['current']['avg_duration']),
        SiteVisitorAnalyticsService::formatDuration($stats['previous']['avg_duration']),
        $stats['change']['avg_duration'] . '%'
    ]);
    fputcsv($out, [
        'Bounce Rate',
        $stats['current']['bounce_rate'] . '%',
        $stats['previous']['bounce_rate'] . '%',
        ($stats['change']['bounce_rate'] > 0 ? '+' : '') . $stats['change']['bounce_rate'] . ' pts'
    ]);
    fputcsv($out, []);

    // 2. Traffic Sources
    fputcsv($out, ['=== TRAFFIC SOURCES ===']);
    fputcsv($out, ['Source', 'Visits', 'Share %']);
    foreach ($trafficSources as $src) {
        fputcsv($out, [csv_cell_safe($src['label']), $src['count'], $src['percentage'] . '%']);
    }
    fputcsv($out, []);

    // 3. Devices
    fputcsv($out, ['=== DEVICE TYPES ===']);
    fputcsv($out, ['Device', 'Visits', 'Share %']);
    foreach ($deviceStats as $devKey => $devData) {
        fputcsv($out, [ucfirst($devKey), $devData['count'], $devData['percentage'] . '%']);
    }
    fputcsv($out, []);

    // 4. Top Pages
    fputcsv($out, ['=== TOP VISITED PAGES ===']);
    fputcsv($out, ['Page Path', 'Page Title', 'Pageviews', 'Unique Visitors', 'Share %']);
    foreach ($topPages as $page) {
        fputcsv($out, [
            csv_cell_safe($page['page_path']),
            csv_cell_safe($page['page_title']),
            $page['views'],
            $page['unique_visitors'],
            $page['percentage'] . '%'
        ]);
    }
    fputcsv($out, []);

    // 5. Locations
    fputcsv($out, ['=== VISITOR LOCATIONS (TOP COUNTRIES) ===']);
    fputcsv($out, ['Country Code', 'Country Name', 'Visits', 'Share %']);
    foreach ($locationStats['countries'] as $c) {
        fputcsv($out, [csv_cell_safe($c['code']), csv_cell_safe($c['name']), $c['count'], $c['percentage'] . '%']);
    }
    fputcsv($out, []);

    // 6. Detailed Session Records
    fputcsv($out, ['=== DETAILED VISITOR SESSIONS (UP TO 500) ===']);
    fputcsv($out, [
        'Timestamp', 'Session ID', 'Visitor / User', 'User Role', 'User Email',
        'Country', 'City', 'Device', 'OS', 'Browser', 'Source', 'Entry Page', 'Exit Page', 'Pageviews', 'Duration (s)'
    ]);
    foreach ($sessionsData['records'] as $sess) {
        fputcsv($out, [
            $sess['first_seen_at'],
            csv_cell_safe($sess['session_id']),
            csv_cell_safe($sess['display_name'] ?? 'Guest Visitor'),
            csv_cell_safe($sess['display_role'] ?? 'Guest'),
            csv_cell_safe($sess['display_email'] ?? ''),
            csv_cell_safe($sess['country_name']),
            csv_cell_safe($sess['city'] ?? '-'),
            csv_cell_safe(ucfirst((string)$sess['device_type'])),
            csv_cell_safe($sess['os']),
            csv_cell_safe($sess['browser']),
            csv_cell_safe(ucfirst((string)$sess['traffic_source'])),
            csv_cell_safe($sess['entry_page']),
            csv_cell_safe($sess['exit_page'] ?? $sess['entry_page']),
            $sess['pageviews_count'],
            $sess['duration_seconds']
        ]);
    }

    fclose($out);
    exit;
}

// -------------------------------------------------------------
// 2. PDF / PRINT REPORT
// -------------------------------------------------------------
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($brandName) ?> - Site Visitors Analytics Report</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            margin: 0;
            padding: 24px;
            color: #1e293b;
            background: #fff;
            font-size: 13px;
            line-height: 1.5;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .report-header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }
        .report-header p {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-primary { background: #e0e7ff; color: #4338ca; }
        .grid-kpis {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .kpi-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
        }
        .kpi-title {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }
        .kpi-value {
            font-size: 20px;
            font-weight: 800;
            margin: 4px 0;
            color: #0f172a;
        }
        .kpi-change {
            font-size: 11px;
            font-weight: 700;
        }
        .text-up { color: #16a34a; }
        .text-down { color: #dc2626; }
        .text-neutral { color: #64748b; }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            margin: 20px 0 8px;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 12px;
        }
        th, td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            font-weight: 700;
            color: #334155;
        }
        .row-split {
            display: flex;
            gap: 16px;
        }
        .col-half {
            flex: 1;
        }
        .print-btn {
            background: #4f46e5;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px; display: flex; justify-content: flex-end; gap: 8px;">
        <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
        <button class="print-btn" style="background:#64748b;" onclick="window.close()">Close</button>
    </div>

    <div class="report-header">
        <div>
            <h1><?= htmlspecialchars($brandName) ?> — Site Visitors Analytics</h1>
            <p>Date Range: <strong><?= htmlspecialchars($fromDate) ?></strong> to <strong><?= htmlspecialchars($toDate) ?></strong> (<?= (int)$stats['days'] ?> days) · Compared to previous <?= (int)$stats['days'] ?> days</p>
        </div>
        <div style="text-align: right;">
            <span class="badge badge-primary">Admin Analytics Export</span>
            <p style="margin-top: 4px;">Generated on <?= date('M j, Y H:i:s') ?></p>
        </div>
    </div>

    <div class="grid-kpis">
        <div class="kpi-box">
            <div class="kpi-title">Total Visits</div>
            <div class="kpi-value"><?= number_format($stats['current']['visits']) ?></div>
            <div class="kpi-change <?= $stats['change']['visits'] >= 0 ? 'text-up' : 'text-down' ?>">
                <?= $stats['change']['visits'] >= 0 ? '+' : '' ?><?= $stats['change']['visits'] ?>% vs prev period
            </div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Unique Visitors</div>
            <div class="kpi-value"><?= number_format($stats['current']['unique_visitors']) ?></div>
            <div class="kpi-change <?= $stats['change']['unique_visitors'] >= 0 ? 'text-up' : 'text-down' ?>">
                <?= $stats['change']['unique_visitors'] >= 0 ? '+' : '' ?><?= $stats['change']['unique_visitors'] ?>% vs prev period
            </div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Total Pageviews</div>
            <div class="kpi-value"><?= number_format($stats['current']['pageviews']) ?></div>
            <div class="kpi-change <?= $stats['change']['pageviews'] >= 0 ? 'text-up' : 'text-down' ?>">
                <?= $stats['change']['pageviews'] >= 0 ? '+' : '' ?><?= $stats['change']['pageviews'] ?>% vs prev period
            </div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Avg Duration</div>
            <div class="kpi-value"><?= SiteVisitorAnalyticsService::formatDuration($stats['current']['avg_duration']) ?></div>
            <div class="kpi-change <?= $stats['change']['avg_duration'] >= 0 ? 'text-up' : 'text-down' ?>">
                <?= $stats['change']['avg_duration'] >= 0 ? '+' : '' ?><?= $stats['change']['avg_duration'] ?>% vs prev period
            </div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Bounce Rate</div>
            <div class="kpi-value"><?= $stats['current']['bounce_rate'] ?>%</div>
            <div class="kpi-change <?= $stats['change']['bounce_rate'] <= 0 ? 'text-up' : 'text-down' ?>">
                <?= $stats['change']['bounce_rate'] > 0 ? '+' : '' ?><?= $stats['change']['bounce_rate'] ?> pts vs prev
            </div>
        </div>
    </div>

    <div class="row-split">
        <div class="col-half">
            <div class="section-title">Traffic Sources</div>
            <table>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th style="width: 80px; text-align: right;">Visits</th>
                        <th style="width: 80px; text-align: right;">Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trafficSources as $src): ?>
                    <tr>
                        <td><?= htmlspecialchars($src['label']) ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format($src['count']) ?></td>
                        <td style="text-align: right;"><?= $src['percentage'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="col-half">
            <div class="section-title">Device Breakdown</div>
            <table>
                <thead>
                    <tr>
                        <th>Device Type</th>
                        <th style="width: 80px; text-align: right;">Visits</th>
                        <th style="width: 80px; text-align: right;">Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deviceStats as $dev => $d): ?>
                    <tr>
                        <td><?= htmlspecialchars(ucfirst($dev)) ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format($d['count']) ?></td>
                        <td style="text-align: right;"><?= $d['percentage'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section-title">Top Visited Pages</div>
    <table>
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Page Path</th>
                <th>Page Title</th>
                <th style="width: 90px; text-align: right;">Pageviews</th>
                <th style="width: 110px; text-align: right;">Unique Visitors</th>
                <th style="width: 80px; text-align: right;">Share %</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($topPages)): ?>
            <tr><td colspan="6" style="text-align: center; color: #94a3b8;">No pageview data recorded for this period.</td></tr>
            <?php else: ?>
            <?php foreach ($topPages as $i => $page): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><code><?= htmlspecialchars($page['page_path']) ?></code></td>
                <td><?= htmlspecialchars($page['page_title']) ?></td>
                <td style="text-align: right; font-weight: 700;"><?= number_format($page['views']) ?></td>
                <td style="text-align: right;"><?= number_format($page['unique_visitors']) ?></td>
                <td style="text-align: right;"><?= $page['percentage'] ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row-split">
        <div class="col-half">
            <div class="section-title">Visitor Locations (Top Countries)</div>
            <table>
                <thead>
                    <tr>
                        <th>Country</th>
                        <th style="width: 80px; text-align: right;">Visits</th>
                        <th style="width: 80px; text-align: right;">Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locationStats['countries'] as $c): ?>
                    <tr>
                        <td><?= $c['flag'] ?> <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['code']) ?>)</td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format($c['count']) ?></td>
                        <td style="text-align: right;"><?= $c['percentage'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="col-half">
            <div class="section-title">Browsers &amp; Operating Systems</div>
            <table>
                <thead>
                    <tr>
                        <th>Browser / OS</th>
                        <th style="width: 80px; text-align: right;">Visits</th>
                        <th style="width: 80px; text-align: right;">Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($browserOs['browsers'], 0, 5) as $b): ?>
                    <tr>
                        <td>Browser: <?= htmlspecialchars($b['name']) ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format($b['count']) ?></td>
                        <td style="text-align: right;"><?= $b['percentage'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($browserOs['os'], 0, 5) as $o): ?>
                    <tr>
                        <td>OS: <?= htmlspecialchars($o['name']) ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= number_format($o['count']) ?></td>
                        <td style="text-align: right;"><?= $o['percentage'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

if (class_exists('Dompdf\Dompdf') && class_exists('Dompdf\Options')) {
    try {
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("visitor_analytics_{$fromDate}_to_{$toDate}.pdf", ['Attachment' => true]);
        exit;
    } catch (Throwable $e) {
        // Fallback to print-ready page
    }
}

// Fallback: render print-ready HTML page with auto-print
echo $html;
exit;
