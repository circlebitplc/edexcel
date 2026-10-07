<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\NotificationCenterService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class CommunicationHubHardeningTest extends TestCase
{
    public function testProcessQueueClaimsRecipientBeforeSend(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/src/Services/CommunicationHubService.php') ?: '';
        $this->assertStringContainsString("status='processing'", $src);
        $this->assertStringContainsString('AND status=\'queued\'', $src);
        $this->assertStringContainsString("status='failed'", $src);
        $this->assertStringContainsString('sms_send_last_error', $src);
        $this->assertStringContainsString('send_whatsapp_last_error', $src);
        $this->assertStringContainsString('$nid > 0', $src);
    }

    public function testConfirmBulkClaimsPreviewJob(): void
    {
        $file = file_get_contents(dirname(__DIR__, 2).'/src/Services/CommunicationHubService.php') ?: '';
        $this->assertStringContainsString("status='preview'", $file);
        $this->assertStringContainsString('confirm_token=NULL', $file);
        $this->assertStringContainsString('already confirmed', strtolower($file));
    }

    public function testBatchPhoneHelpersExist(): void
    {
        $ref = new ReflectionClass(CommunicationHubService::class);
        $this->assertTrue($ref->hasMethod('studentPhones'));
        $this->assertTrue($ref->hasMethod('parentPhones'));
        $this->assertTrue($ref->getMethod('studentPhones')->isPrivate());
    }

    public function testMarkReadUpdatesOnlyOwnedRows(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/src/Services/NotificationCenterService.php') ?: '';
        $this->assertMatchesRegularExpression('/UPDATE notification_center\s+SET is_read = 1[\s\S]*?WHERE id = \? AND audience = \? AND user_id = \?/', $src);
        $this->assertMatchesRegularExpression('/UPDATE notification_center\s+SET is_read = 1[\s\S]*?WHERE audience = \? AND user_id = \? AND is_read = 0/', $src);
        $this->assertDoesNotMatchRegularExpression('/UPDATE notification_center\s+SET is_read = 1[\s\S]*?OR user_id IS NULL[\s\S]*?is_read = 0/', $src);
        // Listing may still show broadcasts as unread (SELECT path).
        $this->assertStringContainsString('(user_id = ? OR user_id IS NULL)', $src);
        $this->assertContains('payments', NotificationCenterService::CATEGORIES);
    }

    public function testLegacyQueueUsesPdoFirstForWhatsapp(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/tools/communication_queue.php') ?: '';
        $this->assertStringContainsString("send_whatsapp(\$pdo, (string)\$row['recipient'], (string)\$row['body'], 'communication')", $src);
        $this->assertStringContainsString('sms_send_last_error', $src);
    }

    public function testWhatsappLastErrorHelperExists(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/config/notifications.php') ?: '';
        $this->assertStringContainsString('function send_whatsapp_last_error', $src);
    }
}
