<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/timetable_services.php';

function generate_future_entries($pdo, $look_ahead_days = 7) {
    $services=TimetableServiceFactory::services($pdo);
    return $services['recurring']->generateFutureEntries((int)$look_ahead_days);
}
