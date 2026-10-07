<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class V2SmokeTest extends TestCase
{
    /**
     * @dataProvider v2ClassProvider
     */
    public function testV2ClassExists(string $class): void
    {
        $this->assertTrue(class_exists($class), $class . ' should exist');
    }

    /**
     * @return list<array{0:string}>
     */
    public function v2ClassProvider(): array
    {
        return [
            [\Edexcel\Services\BackupService::class],
            [\Edexcel\Services\SystemHealthService::class],
            [\Edexcel\Services\HealthAlertService::class],
            [\Edexcel\Services\AdminTotpService::class],
            [\Edexcel\Services\AppLogger::class],
            [\Edexcel\Services\SecurityEventService::class],
            [\Edexcel\Services\AssessmentService::class],
            [\Edexcel\Services\AssessmentScoring::class],
            [\Edexcel\Services\AssessmentAttemptService::class],
            [\Edexcel\Services\AssessmentMarkingService::class],
            [\Edexcel\Services\TopicMasteryService::class],
            [\Edexcel\Services\AdmissionAuth::class],
            [\Edexcel\Services\LeadService::class],
            [\Edexcel\Services\AdmissionLifecycleService::class],
            [\Edexcel\Services\ClassAllocationService::class],
            [\Edexcel\Services\AdmissionAnalyticsService::class],
            [\Edexcel\Services\AdmissionAssistantService::class],
            [\Edexcel\Services\PublicCatalogueService::class],
            [\Edexcel\Services\CommunicationHubService::class],
            [\Edexcel\Services\CommunicationThreadService::class],
            [\Edexcel\Services\MessageTemplateService::class],
            [\Edexcel\Services\CommunicationAiAssistant::class],
            [\Edexcel\Services\CommunicationAnalyticsService::class],
            [\Edexcel\Services\CommunicationEventService::class],
            [\Edexcel\Services\CommunicationPreferenceService::class],
            [\Edexcel\Services\CommunicationAuth::class],
            [\Edexcel\Services\CommunicationRulePackService::class],
            [\Edexcel\Services\SupportTicketService::class],
            [\Edexcel\Services\ParentCommunicationTimelineService::class],
            [\Edexcel\Services\ClassOperationsService::class],
            [\Edexcel\Services\StaffCommunicationWorkbenchService::class],
        ];
    }
}
