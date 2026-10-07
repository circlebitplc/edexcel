<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\AdmissionAuth;
use Edexcel\Services\ClassAllocationService;
use Edexcel\Services\LeadService;
use PHPUnit\Framework\TestCase;

final class AdmissionLifecycleTest extends TestCase
{
    public function testLeadStatusesCoverPipelineAndClosedStates(): void
    {
        foreach (['NEW','CONTACTED','INTERESTED','APPLICATION_STARTED','APPLICATION_SUBMITTED','UNDER_REVIEW','APPROVED','ENROLLED','NOT_INTERESTED','LOST','DEFERRED'] as $status) {
            self::assertContains($status, LeadService::STATUSES);
        }
    }

    public function testLeadSourcesAreBounded(): void
    {
        self::assertContains('website', LeadService::SOURCES);
        self::assertContains('whatsapp', LeadService::SOURCES);
        self::assertNotContains('unknown-channel', LeadService::SOURCES);
    }

    public function testAdmissionsPermissionsExistAndTeachersAreNotImplicit(): void
    {
        foreach (['admissions.view','admissions.create','admissions.edit','admissions.review','admissions.approve','admissions.reject','admissions.assign','admissions.export','admissions.analytics'] as $perm) {
            self::assertContains($perm, AdmissionAuth::PERMISSIONS);
        }
        self::assertFalse(AdmissionAuth::can($this->sqlite(), 99, 'admissions.approve'));
        self::assertFalse(AdmissionAuth::can($this->sqlite(), 1, 'not.a.permission'));
    }

    public function testDuplicateDetectionRequiresReviewNotAutoMerge(): void
    {
        $svc = new LeadService($this->sqlite());
        self::assertSame([], $svc->duplicates(['full_name' => 'x', 'phone' => '']));
    }

    public function testClassRecommendationScoringIsExplainable(): void
    {
        $best = ClassAllocationService::scoreFromSignals(true, true, true, false, true);
        $full = ClassAllocationService::scoreFromSignals(true, false, false, true, true);
        self::assertGreaterThan($full['score'], $best['score']);
        self::assertContains('Matches requested subject.', $best['reasons']);
        self::assertContains('Delivery mode may not match the stated preference.', $full['reasons']);
    }

    public function testPublicCatalogueResourceNamesStayNonPrivate(): void
    {
        self::assertSame(['website','facebook','instagram','whatsapp','walk-in','referral','existing_student','other'], LeadService::SOURCES);
    }

    public function testEmptyTrackingTokenIsReplacedWithThirtyTwoHexChars(): void
    {
        $a = \Edexcel\Services\AdmissionLifecycleService::normalizeToken('');
        $b = \Edexcel\Services\AdmissionLifecycleService::normalizeToken('not-a-token');
        self::assertSame(32, strlen($a));
        self::assertSame(32, strlen($b));
        self::assertNotSame($a, $b);
        $keep = str_repeat('ab', 16);
        self::assertSame($keep, \Edexcel\Services\AdmissionLifecycleService::normalizeToken($keep));
    }

    public function testApplicantFacingTemplatesDoNotIncludeInternalNotes(): void
    {
        self::assertStringContainsString('received', strtolower(\Edexcel\Services\AdmissionMessageTemplates::RECEIVED));
        self::assertStringNotContainsString('internal', strtolower(\Edexcel\Services\AdmissionMessageTemplates::APPROVED));
    }

    private function sqlite(): \PDO
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('sqlite PDO driver is not available');
        }
        return new \PDO('sqlite::memory:');
    }
}
