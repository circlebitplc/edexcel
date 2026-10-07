<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\AdminTotpService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AdminTotpHotpTest extends TestCase
{
    private AdminTotpService $svc;

    protected function setUp(): void
    {
        $this->svc = new AdminTotpService(new PDO('sqlite::memory:'));
    }

    public function testGenerateSecretIsBase32Alphabet(): void
    {
        $gen = new ReflectionMethod(AdminTotpService::class, 'generateSecret');
        $gen->setAccessible(true);
        $secret = $gen->invoke($this->svc, 20);

        $this->assertSame(20, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testHotpIsSixDigitsAndStable(): void
    {
        $hotp = new ReflectionMethod(AdminTotpService::class, 'hotp');
        $hotp->setAccessible(true);

        $secret = 'JBSWY3DPEHPK3PXP';
        $a = $hotp->invoke($this->svc, $secret, 1);
        $b = $hotp->invoke($this->svc, $secret, 1);
        $c = $hotp->invoke($this->svc, $secret, 2);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $a);
        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    public function testVerifyTotpAcceptsCurrentWindowCode(): void
    {
        $hotp = new ReflectionMethod(AdminTotpService::class, 'hotp');
        $hotp->setAccessible(true);
        $verify = new ReflectionMethod(AdminTotpService::class, 'verifyTotp');
        $verify->setAccessible(true);

        $secret = 'JBSWY3DPEHPK3PXP';
        $slice = (int)floor(time() / 30);
        $code = $hotp->invoke($this->svc, $secret, $slice);

        $this->assertTrue($verify->invoke($this->svc, $secret, $code, 1));
        $this->assertFalse($verify->invoke($this->svc, $secret, '000000', 0));
        $this->assertFalse($verify->invoke($this->svc, $secret, 'abcdef', 1));
        $this->assertFalse($verify->invoke($this->svc, $secret, '123', 1));
    }

    public function testVerifyTotpAcceptsAdjacentWindow(): void
    {
        $hotp = new ReflectionMethod(AdminTotpService::class, 'hotp');
        $hotp->setAccessible(true);
        $verify = new ReflectionMethod(AdminTotpService::class, 'verifyTotp');
        $verify->setAccessible(true);

        $secret = 'JBSWY3DPEHPK3PXP';
        $slice = (int)floor(time() / 30);
        $prev = $hotp->invoke($this->svc, $secret, $slice - 1);

        $this->assertTrue($verify->invoke($this->svc, $secret, $prev, 1));
    }
}
