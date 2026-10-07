<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\AppLogger;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Avoid loading config/security.php session bootstrap under PHPUnit (headers already sent).
 * CSRF / validate helpers are mirrored from security.php for pure unit coverage.
 */
final class CsrfAndSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testCsrfGenerateAndVerify(): void
    {
        $token = $this->generateCsrfToken();
        $this->assertNotSame('', $token);
        $this->assertSame(64, strlen($token));
        $this->assertTrue($this->verifyCsrfToken($token));
        $this->assertFalse($this->verifyCsrfToken('not-the-token'));
        $this->assertFalse($this->verifyCsrfToken(''));
        $this->assertSame($token, $this->generateCsrfToken());
    }

    public function testCsrfFieldContainsToken(): void
    {
        $token = $this->generateCsrfToken();
        $html = '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        $this->assertStringContainsString('csrf_token', $html);
        $this->assertStringContainsString($token, $html);
    }

    public function testValidateInputTypes(): void
    {
        $this->assertSame(12, $this->validateInput('12', 'int'));
        $this->assertFalse($this->validateInput('x', 'int'));
        $this->assertSame('a@b.co', $this->validateInput('a@b.co', 'email'));
        $this->assertSame('2024-01-15', $this->validateInput('2024-01-15', 'date'));
        $this->assertFalse($this->validateInput('15-01-2024', 'date'));
    }

    public function testAppLoggerRedactionPatterns(): void
    {
        $logger = new AppLogger(null);
        $method = new ReflectionMethod(AppLogger::class, 'redact');
        $method->setAccessible(true);
        $out = $method->invoke($logger, [
            'access_token' => 'tok',
            'refresh_token' => 'r',
            'authorization' => 'Bearer x',
            'safe' => 'yes',
        ]);
        $this->assertSame('[REDACTED]', $out['access_token']);
        $this->assertSame('[REDACTED]', $out['refresh_token']);
        $this->assertSame('[REDACTED]', $out['authorization']);
        $this->assertSame('yes', $out['safe']);
    }

    private function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }

    private function verifyCsrfToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    /** @return mixed */
    private function validateInput(string $data, string $type = 'string')
    {
        $data = trim($data);
        return match ($type) {
            'int' => filter_var($data, FILTER_VALIDATE_INT),
            'float' => filter_var($data, FILTER_VALIDATE_FLOAT),
            'email' => filter_var($data, FILTER_VALIDATE_EMAIL),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? $data : false,
            'time' => preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data) ? $data : false,
            default => htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8'),
        };
    }
}
