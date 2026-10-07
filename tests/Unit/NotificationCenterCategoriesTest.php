<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\NotificationCenterService;
use PHPUnit\Framework\TestCase;

final class NotificationCenterCategoriesTest extends TestCase
{
    public function testAllowedCategoriesList(): void
    {
        $cats = NotificationCenterService::allowedCategories();
        $this->assertContains('payments', $cats);
        $this->assertContains('classes', $cats);
        $this->assertContains('recordings', $cats);
        $this->assertContains('system', $cats);
        $this->assertSame(NotificationCenterService::CATEGORIES, $cats);
    }

    public function testIsAllowedCategory(): void
    {
        $this->assertTrue(NotificationCenterService::isAllowedCategory('payments'));
        $this->assertTrue(NotificationCenterService::isAllowedCategory('SYSTEM'));
        $this->assertFalse(NotificationCenterService::isAllowedCategory('spam'));
        $this->assertFalse(NotificationCenterService::isAllowedCategory(''));
    }
}
