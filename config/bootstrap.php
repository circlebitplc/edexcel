<?php
declare(strict_types=1);

/**
 * Canonical application bootstrap for timetable modules.
 * Safe to require_once from pages/AJAX/API endpoints.
 */
require_once __DIR__ . '/load_env.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/response_security.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/timetable_services.php';
require_once __DIR__ . '/campus.php';
require_once __DIR__ . '/classroom.php';
require_once __DIR__ . '/courso.php';
require_once __DIR__ . '/online_lesson.php';
require_once __DIR__ . '/ops.php';
$__eck_request_started = microtime(true);
if (!function_exists('eck_register_performance_monitor')) {
    function eck_register_performance_monitor(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;
        register_shutdown_function(static function (): void {
            global $pdo, $__eck_request_started;
            if (!isset($__eck_request_started) || !($pdo instanceof PDO)) {
                return;
            }
            try {
                if (class_exists(\Edexcel\Services\PerformanceMonitor::class)) {
                    $duration = (microtime(true) - (float)$__eck_request_started) * 1000;
                    (new \Edexcel\Services\PerformanceMonitor($pdo))->record(
                        (string)($_SERVER['REQUEST_URI'] ?? 'cli'),
                        (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
                        $duration,
                        http_response_code()
                    );
                }
            } catch (Throwable $e) {
                // Performance telemetry must never break a request.
            }
        });
    }
}
eck_register_performance_monitor();
if (isset($pdo) && $pdo instanceof PDO) {
    if (function_exists('enforce_active_session')) {
        enforce_active_session($pdo);
    }
    if (ops_schema_should_heal()) {
        ensure_campus_schema($pdo);
        ensure_classroom_schema($pdo);
        ensure_courso_schema($pdo);
        ensure_online_lesson_schema($pdo);
        ensure_ops_schema($pdo);
        if (!function_exists('student_devices')) {
            require_once dirname(__DIR__) . '/student/device_helpers.php';
        }
        $deviceSvc = student_devices($pdo);
        if ($deviceSvc) {
            \Edexcel\Services\StudentDeviceService::ensureSchema($pdo);
        }
        if (function_exists('app_theme_ensure_schema')) {
            app_theme_ensure_schema($pdo);
        }
        if (class_exists(\Edexcel\Services\ClassSessionFeeCalculator::class)) {
            \Edexcel\Services\ClassSessionFeeCalculator::ensureSchema($pdo);
        }
        ops_schema_mark_ok();
    }
    if (function_exists('is_logged_in') && is_logged_in() && function_exists('current_role') && current_role() === 'student') {
        if (!function_exists('student_device_enforce_gate')) {
            require_once dirname(__DIR__) . '/student/device_helpers.php';
        }
        student_device_enforce_gate();
    }
    if (function_exists('classroom_web_reminder_tick')) {
        classroom_web_reminder_tick($pdo);
    }
}
