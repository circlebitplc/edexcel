<?php
declare(strict_types=1);

namespace Edexcel\Tests;

use Edexcel\Http\ApiRequest;
use Edexcel\Services\WebhookService;
use PHPUnit\Framework\TestCase;

final class ApiFoundationTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testPaginationIsBoundedAndCalculated(): void
    {
        $_GET = ['page' => '3', 'per_page' => '1000'];
        self::assertSame(['page'=>3,'per_page'=>100,'offset'=>200], ApiRequest::pagination());
    }

    public function testSortingRejectsUnlistedColumns(): void
    {
        $_GET = ['sort' => 'password', 'order' => 'sideways'];
        self::assertSame(['sort'=>'name','order'=>'asc'], ApiRequest::sorting(['name','created_at'],'name'));
    }

    public function testWebhookSignatureIsVerified(): void
    {
        $body = '{"event":"StudentEnrolled"}';
        $signature = WebhookService::signature($body, 'test-secret');
        self::assertTrue(WebhookService::verify($body, 'test-secret', $signature));
        self::assertFalse(WebhookService::verify($body, 'wrong-secret', $signature));
    }
}
