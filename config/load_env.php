<?php
// config/load_env.php
// Load environment variables from .env (prefer outside the web root).

$envCandidates = [
    dirname(__DIR__, 2) . '/.env', // /home/edexcel.college/.env (preferred)
    __DIR__ . '/../.env',          // public_html/.env (legacy / local)
];
$envFile = null;
foreach ($envCandidates as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $envFile = $candidate;
        break;
    }
}

if ($envFile !== null) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        // Find the first '=' position (supports values with '=' inside)
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue; // Skip lines without '='
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        // Remove quotes if present
        if (strlen($value) > 0 && (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
            ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        if (!isset($_ENV[$key]) && !getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Define constants only if they are not already defined
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'edexcel_timetable');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('APP_ENV')) define('APP_ENV', getenv('APP_ENV') ?: 'production');

$envAppUrl = rtrim(trim((string)(getenv('APP_URL') ?: '')), '/');
$httpHost = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '';
$isBrowserLocal = $httpHost !== '' && (bool)preg_match('#^(localhost|127\.0\.0\.1)$#i', $httpHost);
$envIsLocal = $envAppUrl === '' || $envAppUrl === '/' || (bool)preg_match('#://(localhost|127\.0\.0\.1)#i', $envAppUrl);

if (!$envIsLocal) {
    $resolvedAppUrl = $envAppUrl;
} elseif ($httpHost !== '' && !$isBrowserLocal) {
    $resolvedAppUrl = 'https://' . $httpHost;
} elseif ($isBrowserLocal) {
    $resolvedAppUrl = ($envAppUrl !== '' && $envAppUrl !== '/') ? $envAppUrl : ('http://' . $httpHost);
} else {
    $resolvedAppUrl = 'https://edexcel.college';
}

putenv('APP_URL=' . $resolvedAppUrl);
$_ENV['APP_URL'] = $resolvedAppUrl;
if (!defined('APP_URL')) define('APP_URL', $resolvedAppUrl !== '' ? $resolvedAppUrl : '/');
if (!defined('FEE_PER_STUDENT')) define('FEE_PER_STUDENT', (int)(getenv('FEE_PER_STUDENT') ?: 500));
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', (int)(getenv('SESSION_TIMEOUT') ?: 3600));

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Colombo');
}
