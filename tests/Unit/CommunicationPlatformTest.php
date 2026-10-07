<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\CommunicationAuth;
use Edexcel\Services\CommunicationAiAssistant;
use Edexcel\Services\MessageTemplateService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class CommunicationPlatformTest extends TestCase
{
    public function testTemplateRejectsUnknownVariable(): void
    {
        $pdo = $this->createMock(PDO::class);
        $svc = new MessageTemplateService($pdo);
        $this->expectException(RuntimeException::class);
        $svc->assertVariables('Hello {{fake_token}}');
    }

    public function testTemplateRendersKnownVariables(): void
    {
        $pdo = $this->createMock(PDO::class);
        $svc = new MessageTemplateService($pdo);
        $out = $svc->render('Hi {{student_name}}, class {{class_name}}', [
            'student_name' => 'Amal',
            'class_name' => 'IGCSE Math',
        ]);
        $this->assertSame('Hi Amal, class IGCSE Math', $out);
    }

    public function testBulkConfirmThresholdConstant(): void
    {
        $this->assertSame(25, \Edexcel\Services\CommunicationHubService::BULK_CONFIRM_THRESHOLD);
    }

    public function testMandatoryPreferencesIncludePayments(): void
    {
        $this->assertContains('payments', \Edexcel\Services\CommunicationPreferenceService::MANDATORY);
        $this->assertContains('system', \Edexcel\Services\CommunicationPreferenceService::MANDATORY);
    }

    public function testCommunicationPermissionsListed(): void
    {
        $this->assertContains('communication.broadcast', CommunicationAuth::PERMISSIONS);
        $this->assertContains('communication.templates', CommunicationAuth::PERMISSIONS);
    }

    public function testAiAssistantDoesNotAutoSend(): void
    {
        $ref = new ReflectionClass(CommunicationAiAssistant::class);
        $methods = array_map(static fn($m) => $m->getName(), $ref->getMethods());
        $this->assertContains('draft', $methods);
        $this->assertNotContains('send', $methods);
        $this->assertNotContains('queue', $methods);
    }

    public function testHubAndThreadServicesExist(): void
    {
        $this->assertTrue(class_exists(\Edexcel\Services\CommunicationHubService::class));
        $this->assertTrue(class_exists(\Edexcel\Services\CommunicationThreadService::class));
        $this->assertTrue(class_exists(\Edexcel\Services\AnnouncementService::class));
        $this->assertTrue(class_exists(\Edexcel\Services\CommunicationAnalyticsService::class));
        $this->assertTrue(class_exists(\Edexcel\Services\CommunicationEventService::class));
    }
}
