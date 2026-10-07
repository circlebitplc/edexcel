<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\OnlineLessonService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExternalLinkTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/src/Services/OnlineLessonService.php';
    }

    public function testOnlyHttpAndHttpsLinksAreAccepted(): void
    {
        $this->assertSame(
            'https://edex.college/ppt/Topic_1_1.php',
            OnlineLessonService::normalizeExternalUrl('  https://edex.college/ppt/Topic_1_1.php  ')
        );
        $this->assertSame(
            'http://edex.college/notes',
            OnlineLessonService::normalizeExternalUrl('http://edex.college/notes')
        );
        foreach (['javascript:alert(1)', 'data:text/html,hi', 'file:///c/secret', 'vbscript:msgbox', 'https://', ''] as $bad) {
            try {
                OnlineLessonService::normalizeExternalUrl($bad);
                $this->fail('Expected rejection for ' . $bad);
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testLinkLabelHidesTheScheme(): void
    {
        $this->assertSame(
            'edex.college/ppt/Topic_1_1.php',
            OnlineLessonService::externalLinkLabel('https://edex.college/ppt/Topic_1_1.php')
        );
    }

    public function testExternalLinkStaysItsOwnItemType(): void
    {
        $this->assertSame('external_link', OnlineLessonService::normalizeItemType('external_link'));
        $this->assertSame('page', OnlineLessonService::normalizeItemType('page'));
        $this->assertSame('activity', OnlineLessonService::normalizeItemType('activity'));
        $this->assertSame('video', OnlineLessonService::normalizeItemType('video'));
    }

    public function testRequiredLinkBlocksTheNextItemUntilOpened(): void
    {
        $items = [
            ['id' => 1, 'required' => 1],
            ['id' => 2, 'required' => 1],
        ];
        $this->assertFalse(OnlineLessonService::canOpenItem($items, [], 2, true));
        $this->assertTrue(OnlineLessonService::canOpenItem($items, [1], 2, true));
        $optional = [
            ['id' => 1, 'required' => 0],
            ['id' => 2, 'required' => 1],
        ];
        $this->assertTrue(OnlineLessonService::canOpenItem($optional, [], 2, true));
    }
}
