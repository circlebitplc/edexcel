<?php
declare(strict_types=1);

namespace Edexcel\Services;

use PDO;
use Throwable;

/**
 * Central SMS Service
 *
 * Provides a unified application interface for sending SMS messages.
 * Automatically handles provider routing (iPromo vs SMS-Gate),
 * phone number normalization and validation for Sri Lanka,
 * gate checks (enabled/disabled), secure credential loading, logging,
 * and structured results.
 *
 * Example usage:
 *   $result = SmsService::send($phone, $message, $pdo);
 *   if ($result['success']) { ... }
 */
final class SmsService
{
    /**
     * Send an SMS message through the central SMS service.
     * Supports explicit gateway selection ('sms_gate_android' or 'ipromo').
     *
     * @param string        $phoneNumber  Raw phone number in any supported format (e.g. 0771234567, +94771234567)
     * @param string        $message      Message body
     * @param mixed         $pdoOrGateway Database connection (PDO) OR string gateway identifier ('sms_gate_android', 'ipromo')
     * @param string        $context      Optional audit context tag (e.g. 'otp', 'payment_sms', 'test', 'bulk_sms:...')
     * @param int|null      $sentBy       User ID of the sender/actor, if any
     * @param string|null   $gateway      Explicit gateway identifier ('sms_gate_android' or 'ipromo'). Overrides global setting.
     * @return array{success:bool, provider:string, message_id?:string, error?:string, response?:array<string,mixed>, skipped?:bool}
     */
    public static function send(
        string $phoneNumber,
        string $message,
        mixed $pdoOrGateway = null,
        string $context = '',
        ?int $sentBy = null,
        ?string $gateway = null
    ): array {
        $pdo = null;
        if (is_string($pdoOrGateway)) {
            $gateway = $pdoOrGateway;
        } elseif ($pdoOrGateway instanceof PDO) {
            $pdo = $pdoOrGateway;
        }

        if ($pdo === null) {
            global $pdo;
        }

        self::loadGatewayHelpers();

        $message = trim($message);
        if ($message === '') {
            return [
                'success'  => false,
                'provider' => 'none',
                'error'    => 'Message content cannot be empty.',
            ];
        }

        // Determine target provider: explicit gateway parameter takes precedence
        if ($gateway !== null && trim($gateway) !== '') {
            $activeProvider = self::normalizeGatewayIdentifier($gateway);
            if ($activeProvider === '') {
                return [
                    'success'  => false,
                    'provider' => 'invalid',
                    'error'    => 'Invalid or unsupported SMS gateway specified: ' . htmlspecialchars($gateway, ENT_QUOTES, 'UTF-8'),
                ];
            }
        } else {
            $activeProvider = function_exists('sms_active_provider') && $pdo instanceof PDO
                ? sms_active_provider($pdo)
                : 'sms_gate_android';
            $activeProvider = self::normalizeGatewayIdentifier($activeProvider);
        }

        // ─────────────────────────────────────────────────────────────
        // 1. Route to iPromo SMS Provider
        // ─────────────────────────────────────────────────────────────
        if ($activeProvider === 'ipromo') {
            if (!$pdo instanceof PDO) {
                return [
                    'success'  => false,
                    'provider' => 'ipromo',
                    'error'    => 'Database connection is required for iPromo SMS.',
                ];
            }

            // Check if gateway is enabled in Admin Settings
            if (function_exists('ipromo_enabled') && !ipromo_enabled($pdo)) {
                if (function_exists('sms_log_write')) {
                    sms_log_write($pdo, $phoneNumber, $message, 'ipromo', 'skipped', '', 'SMS Gateway is disabled in settings.', $sentBy, $context ?: null);
                }
                return [
                    'success'  => false,
                    'provider' => 'ipromo',
                    'error'    => 'iPromo SMS Gateway is currently disabled in Admin Settings.',
                    'skipped'  => true,
                ];
            }

            // Validate and normalize Sri Lankan phone number
            $normalized = self::normalizeSriLankanNumber($phoneNumber);
            if ($normalized === '') {
                $err = 'Invalid mobile number. Please provide a valid Sri Lankan mobile number (e.g. 077XXXXXXX).';
                if (function_exists('sms_log_write')) {
                    sms_log_write($pdo, $phoneNumber, $message, 'ipromo', 'failed', '', $err, $sentBy, $context ?: null);
                }
                return [
                    'success'  => false,
                    'provider' => 'ipromo',
                    'error'    => $err,
                ];
            }

            $providerInstance = IPromoSmsProvider::fromSettings($pdo);
            if (!$providerInstance->isConfigured()) {
                $err = 'iPromo SMS Gateway credentials are not configured in Admin Settings.';
                if (function_exists('sms_log_write')) {
                    sms_log_write($pdo, $normalized, $message, 'ipromo', 'failed', '', $err, $sentBy, $context ?: null);
                }
                return [
                    'success'  => false,
                    'provider' => 'ipromo',
                    'error'    => $err,
                ];
            }

            $result = $providerInstance->send($normalized, $message);
            $msgId = (string)($result['message_id'] ?? '');

            if (!empty($result['success'])) {
                if (function_exists('sms_log_write')) {
                    sms_log_write($pdo, $normalized, $message, 'ipromo', 'sent', $msgId, null, $sentBy, $context ?: null);
                }
                return [
                    'success'    => true,
                    'provider'   => 'ipromo',
                    'message_id' => $msgId,
                    'response'   => [
                        'raw_code' => $result['raw_code'] ?? 200,
                        'status'   => 'sent',
                    ],
                ];
            }

            // Strict no-fallback: iPromo failure returns failure directly
            $err = (string)($result['error'] ?? 'SMS sending failed through iPromo Marketing.');
            if (function_exists('sms_log_write')) {
                sms_log_write($pdo, $normalized, $message, 'ipromo', 'failed', $msgId, $err, $sentBy, $context ?: null);
            }
            return [
                'success'  => false,
                'provider' => 'ipromo',
                'error'    => $err,
            ];
        }

        // ─────────────────────────────────────────────────────────────
        // 2. Route to SMS-Gate Android Gateway
        // ─────────────────────────────────────────────────────────────
        if (!function_exists('sms_send')) {
            return [
                'success'  => false,
                'provider' => 'sms_gate_android',
                'error'    => 'SMS-Gate provider functions are unavailable.',
            ];
        }

        $normalized = self::normalizeSriLankanNumber($phoneNumber);
        if ($normalized === '') {
            return [
                'success'  => false,
                'provider' => 'sms_gate_android',
                'error'    => 'Invalid mobile number. Please provide a valid Sri Lankan mobile number.',
            ];
        }

        $ok = sms_send($pdo, $normalized, $message);
        $lastId  = function_exists('sms_send_last_id') ? sms_send_last_id() : '';
        $lastErr = function_exists('sms_send_last_error') ? sms_send_last_error() : '';

        if ($pdo instanceof PDO && function_exists('sms_log_write')) {
            sms_log_write(
                $pdo,
                $normalized,
                $message,
                'sms-gate',
                $ok ? 'sent' : 'failed',
                $lastId,
                $ok ? null : $lastErr,
                $sentBy,
                $context ?: null
            );
        }

        if ($ok) {
            return [
                'success'    => true,
                'provider'   => 'sms_gate_android',
                'message_id' => $lastId,
                'response'   => ['status' => 'sent'],
            ];
        }

        // Strict no-fallback: SMS-Gate failure returns failure directly
        return [
            'success'  => false,
            'provider' => 'sms_gate_android',
            'error'    => $lastErr !== '' ? $lastErr : 'SMS sending failed through SMS-Gate (Android).',
        ];
    }

    /**
     * Normalize gateway internal identifier.
     * Returns canonical string: 'sms_gate_android' or 'ipromo', or '' on invalid.
     */
    public static function normalizeGatewayIdentifier(?string $gateway): string
    {
        $raw = strtolower(trim((string)$gateway));
        return match ($raw) {
            'ipromo', 'ipromo_marketing' => 'ipromo',
            'sms_gate_android', 'sms-gate', 'smsgate', 'android' => 'sms_gate_android',
            default => '',
        };
    }

    /**
     * Get user-friendly label for an internal gateway identifier.
     */
    public static function getGatewayLabel(string $gateway): string
    {
        $norm = self::normalizeGatewayIdentifier($gateway);
        return match ($norm) {
            'ipromo' => 'iPromo Marketing',
            'sms_gate_android' => 'SMS-Gate (Android)',
            default => 'Unknown Gateway',
        };
    }

    /**
     * Inspect all configured gateways, their availability, and safe status information.
     * Never exposes API keys, tokens, or passwords.
     *
     * @return array<string,array{id:string,name:string,available:bool,enabled:bool,configured:bool,status_text:string,badge_class:string,device_info:string,notes:string}>
     */
    public static function getAvailableGateways(?PDO $pdo = null): array
    {
        if ($pdo === null) {
            global $pdo;
        }
        self::loadGatewayHelpers();

        // 1. SMS-Gate Android
        $smsGateCfg = function_exists('sms_gateway_config') && $pdo instanceof PDO ? sms_gateway_config($pdo) : [];
        $smsGateConfigured = !empty($smsGateCfg['sms_gateway_username']) && !empty($smsGateCfg['sms_gateway_password']);
        $smsGateMode = $smsGateCfg['sms_gateway_mode'] ?? 'cloud';
        $smsGateDevice = !empty($smsGateCfg['sms_gateway_device_id']) ? $smsGateCfg['sms_gateway_device_id'] : 'Android Gateway Device';

        // 2. iPromo Marketing
        $ipromoEnabled = function_exists('ipromo_enabled') && $pdo instanceof PDO ? ipromo_enabled($pdo) : false;
        $ipromoConfigured = function_exists('ipromo_configured') && $pdo instanceof PDO ? ipromo_configured($pdo) : false;
        $ipromoCfg = function_exists('ipromo_config') && $pdo instanceof PDO ? ipromo_config($pdo) : [];
        $ipromoSender = $ipromoCfg['sender_id'] ?? '';

        return [
            'sms_gate_android' => [
                'id'          => 'sms_gate_android',
                'name'        => 'SMS-Gate (Android)',
                'available'   => true,
                'enabled'     => true,
                'configured'  => $smsGateConfigured,
                'status_text' => 'Available',
                'badge_class' => 'bg-secondary',
                'device_info' => 'Device: ' . ($smsGateDevice ?: 'Android SMS Gateway') . ' (' . ucfirst($smsGateMode) . ' Mode)',
                'notes'       => 'Direct cellular carrier dispatch via connected Android hardware',
            ],
            'ipromo' => [
                'id'          => 'ipromo',
                'name'        => 'iPromo Marketing',
                'available'   => $ipromoEnabled && $ipromoConfigured,
                'enabled'     => $ipromoEnabled,
                'configured'  => $ipromoConfigured,
                'status_text' => !$ipromoEnabled ? 'Disabled' : ($ipromoConfigured ? 'Available' : 'Incomplete Setup'),
                'badge_class' => $ipromoEnabled ? 'bg-info text-dark' : 'bg-danger text-white',
                'device_info' => $ipromoEnabled && $ipromoSender !== '' ? 'Sender ID: ' . htmlspecialchars($ipromoSender, ENT_QUOTES, 'UTF-8') : ($ipromoSender !== '' ? 'Sender ID: ' . htmlspecialchars($ipromoSender, ENT_QUOTES, 'UTF-8') : 'Sender ID: Not configured'),
                'notes'       => !$ipromoEnabled ? 'This gateway is currently disabled in Admin Settings.' : 'High-speed cloud SMS gateway via iPromo Marketing (Sri Lanka)',
            ],
        ];
    }

    /**
     * Normalizes a Sri Lankan phone number to format '947XXXXXXXX'.
     *
     * Handles:
     * - 0771234567   -> 94771234567
     * - 771234567    -> 94771234567
     * - 94771234567  -> 94771234567
     * - +94771234567 -> 94771234567
     *
     * @param string $raw
     * @return string Normalized 11-digit string, or '' if invalid.
     */
    public static function normalizeSriLankanNumber(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '94' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '94' . $digits;
        }

        if (preg_match('/^947\d{8}$/', $digits)) {
            return $digits;
        }

        return '';
    }

    /**
     * Verify whether a number is a valid Sri Lankan mobile number.
     */
    public static function isValidSriLankanMobile(string $raw): bool
    {
        return self::normalizeSriLankanNumber($raw) !== '';
    }

    /**
     * Helper to ensure sms_gateway.php is loaded.
     */
    private static function loadGatewayHelpers(): void
    {
        if (!function_exists('sms_active_provider')) {
            $file = dirname(__DIR__, 2) . '/config/sms_gateway.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
}
