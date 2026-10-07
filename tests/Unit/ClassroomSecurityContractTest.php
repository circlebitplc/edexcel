<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use Edexcel\Services\ClassroomAccessService;
use PHPUnit\Framework\TestCase;

final class ClassroomSecurityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        require_once $this->root . '/config/classroom.php';
    }

    public function testSetupStateCannotReadOrWriteWhiteboard(): void
    {
        $access = ['code' => ClassroomAccessService::SETUP, 'is_host' => false, 'message' => 'Setup required'];
        $settings = ['whiteboard_enabled' => 1];

        $read = classroom_whiteboard_access($access, $settings, 'GET');
        $write = classroom_whiteboard_access($access, $settings, 'POST', 'stroke', true);

        $this->assertFalse($read['ok']);
        $this->assertFalse($write['ok']);
        $this->assertFalse($read['can_view']);
    }

    public function testPdfTokenCarriesOwnerAndRejectsTampering(): void
    {
        $token = classroom_pdf_download_token(31, 77, 300);
        $decoded = classroom_pdf_verify_download_token($token);

        $this->assertSame(['pdf_id' => 31, 'user_id' => 77], $decoded);
        $this->assertNull(classroom_pdf_verify_download_token($token . 'x'));
    }

    public function testEndpointsUseSharedAccessGateAndPdfBindsTokenOwner(): void
    {
        foreach (['chat.php', 'whiteboard.php', 'heartbeat.php', 'handwriting.php', 'pdf.php', 'control.php'] as $file) {
            $source = (string) file_get_contents($this->root . '/api/classroom/' . $file);
            $this->assertStringContainsString('classroom_api_require_access($access', $source, $file);
        }

        $pdf = (string) file_get_contents($this->root . '/api/classroom/pdf.php');
        $this->assertStringContainsString("(int)\$decoded['user_id'] === \$userId", $pdf);

        $control = (string) file_get_contents($this->root . '/api/classroom/control.php');
        $this->assertStringContainsString('participantState((int)$meeting[\'id\'], $targetId)', $control);
        $this->assertStringContainsString("'allow_speak'", $control);

        $token = (string) file_get_contents($this->root . '/api/classroom/token.php');
        $this->assertStringNotContainsString('touchParticipant(', $token);
    }
}
