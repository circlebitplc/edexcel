<?php
// config/error_handler.php
// Production-safe exception/error handling.

require_once __DIR__ . '/load_env.php';

$eckDisplayEnv = defined('APP_ENV') ? (string)APP_ENV : (string)(getenv('APP_ENV') ?: 'production');
if (strtolower($eckDisplayEnv) !== 'development') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

set_exception_handler(function ($e) {
    error_log('Uncaught Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    try {
        if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            $logger = new \Edexcel\Services\AppLogger($GLOBALS['pdo'] ?? null);
            $logger->critical('uncaught_exception', $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 'error_handler');
        }
    } catch (Throwable $ignored) {
    }
    if (defined('APP_ENV') && APP_ENV === 'development') {
        echo '<pre><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . "\n";
        echo '<strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n";
        echo '<strong>Stack trace:</strong>\n' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        http_response_code(500);
        echo 'An error occurred. Please try again later.';
    }
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $skip = [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE];
    if (defined('E_STRICT')) {
        $skip[] = E_STRICT;
    }
    if (in_array($errno, $skip, true)) {
        error_log(sprintf('PHP notice: %s in %s:%d', $errstr, $errfile, $errline));
        return true;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        if (defined('APP_ENV') && APP_ENV === 'development') {
            echo '<pre><strong>Fatal error:</strong> ' . htmlspecialchars($error['message']) . "\n";
            echo '<strong>File:</strong> ' . htmlspecialchars($error['file']) . ':' . $error['line'] . '</pre>';
        } else {
            http_response_code(500);
            echo 'A critical error occurred.';
        }
    }
});

require_once __DIR__ . '/database.php';
