<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\CommunicationHubService;
use Edexcel\Services\StaffCommunicationWorkbenchService;
use PDO;
use PHPUnit\Framework\TestCase;

final class StaffCommunicationWorkbenchTest extends TestCase
{
    public function testWorkbenchServiceExists(): void
    {
        $this->assertTrue(class_exists(StaffCommunicationWorkbenchService::class));
        $this->assertTrue(method_exists(StaffCommunicationWorkbenchService::class, 'studentTimeline'));
        $this->assertTrue(method_exists(StaffCommunicationWorkbenchService::class, 'searchStudents'));
        $this->assertTrue(method_exists(CommunicationHubService::class, 'retryFailedMatching'));
    }

    public function testListFailedRecipientsAcceptsFiltersSignature(): void
    {
        $ref = new \ReflectionMethod(CommunicationHubService::class, 'listFailedRecipients');
        $this->assertSame(2, $ref->getNumberOfParameters());
    }

    public function testWorkbenchPagesExist(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root.'/admin/communication_workbench.php');
        $this->assertFileExists($root.'/admin/student360.php');
        $src = file_get_contents($root.'/admin/student360.php') ?: '';
        $this->assertStringContainsString('tab=communication', $src);
        $this->assertStringContainsString('StaffCommunicationWorkbenchService', $src);
    }

    public function testOpsConsoleHasFilterControls(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2).'/admin/communication_ops.php') ?: '';
        $this->assertStringContainsString('retry_filtered', $src);
        $this->assertStringContainsString('name="channel"', $src);
        $this->assertStringContainsString('name="from"', $src);
    }
}
