<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClassroomUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testClassroomBootAndListenersAreIdempotent(): void
    {
        $js = (string) file_get_contents($this->root . '/assets/js/classroom.js');

        $this->assertStringContainsString('window.__CK_CLASSROOM_BOOTED__', $js);
        $this->assertStringContainsString('if (wireUi.done) return;', $js);
        $this->assertStringContainsString('if (initMobileClassroom.done) return;', $js);
        $this->assertSame(1, substr_count($js, "querySelectorAll('.ck-sd-btn')"));
        $this->assertSame(1, substr_count($js, "closest('#ckBoardFs, .ck-pill-fs-btn, .ck-board-fs')"));
    }

    public function testRenderedClassroomContainsNoDemoPeopleOrMessages(): void
    {
        $php = (string) file_get_contents($this->root . '/classroom/room.php');

        $this->assertStringNotContainsString('subcard-demo-', $php);
        $this->assertStringNotContainsString('Sir, can you please explain', $php);
        $this->assertStringNotContainsString('onclick="if(typeof window.toggleBoardFullscreen', $php);
        $this->assertStringContainsString("<?php if (\$isHost): ?>", $php);
        $this->assertStringContainsString('id="ckHostPanel"', $php);
    }

    public function testStudentMobileDockHasOneActionPerCapability(): void
    {
        $php = (string) file_get_contents($this->root . '/classroom/room.php');

        foreach (['ckMobBtnBoard', 'ckMobBtnShare', 'ckMobBtnChat', 'ckMobBtnAudio', 'ckMobBtnMore'] as $id) {
            $this->assertSame(1, substr_count($php, 'id="' . $id . '"'), $id . ' must be unique');
        }
        $this->assertStringNotContainsString('id="ckMobBtnTeacher"', $php);

        $css = (string) file_get_contents($this->root . '/assets/css/classroom.css');
        $this->assertStringContainsString('env(safe-area-inset-bottom', $css);
        $this->assertStringContainsString('@media (max-width: 430px)', $css);
    }
}
