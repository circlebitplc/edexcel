<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StudentHistoryAndSystemDocTest extends TestCase
{
    public function testStudentHistoryPageExists(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root.'/student/history.php');
        $src = file_get_contents($root.'/student/history.php') ?: '';
        $this->assertStringContainsString('StaffCommunicationWorkbenchService', $src);
        $this->assertStringContainsString('parent_delivery', $src);
        $this->assertStringContainsString('is_student', $src);
    }

    public function testSystemMdDocumentsCommunicationPlatform(): void
    {
        $md = file_get_contents(dirname(__DIR__, 2).'/SYSTEM.md') ?: '';
        $this->assertStringContainsString('## 21. Notifications & Communication Platform', $md);
        $this->assertStringContainsString('CommunicationHubService', $md);
        $this->assertStringContainsString('communication_queue.php', $md);
        $this->assertStringContainsString('/api/sms/webhook.php', $md);
        $this->assertStringContainsString('/student/history.php', $md);
        $this->assertStringContainsString('033', $md);
        $this->assertStringContainsString('036', $md);
    }
}
