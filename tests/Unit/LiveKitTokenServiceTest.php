<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\LiveKitTokenService;
use PHPUnit\Framework\TestCase;

final class LiveKitTokenServiceTest extends TestCase
{
    public function testTokenRoundTrip(): void
    {
        $jwt = LiveKitTokenService::participantToken(
            'devkey',
            'secretsecretsecretsecretsecret12',
            'u12',
            'Nimal',
            [
                'roomJoin' => true,
                'room' => 'eck-abc',
                'canPublish' => true,
                'canSubscribe' => true,
            ],
            3600,
            '{"role":"student"}'
        );
        $this->assertTrue(LiveKitTokenService::verify($jwt, 'secretsecretsecretsecretsecret12'));
        $this->assertFalse(LiveKitTokenService::verify($jwt, 'wrong'));
        $payload = LiveKitTokenService::decodeUnverified($jwt);
        $this->assertSame('devkey', $payload['iss'] ?? null);
        $this->assertSame('u12', $payload['sub'] ?? null);
        $this->assertSame('eck-abc', $payload['video']['room'] ?? null);
        $this->assertSame('Nimal', $payload['name'] ?? null);
        $parts = explode('.', $jwt);
        $pad = strlen($parts[0]) % 4;
        $headerRaw = $parts[0] . ($pad ? str_repeat('=', 4 - $pad) : '');
        $headerJson = json_decode((string)base64_decode(strtr($headerRaw, '-_', '+/'), true), true);
        $this->assertSame('devkey', $headerJson['kid'] ?? null);
    }
}
