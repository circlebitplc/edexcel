<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AppRailContractTest extends TestCase
{
    public function testAdminNavigationUsesSixTaskGroupsWithoutDroppingRegistries(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/app_menu.php');

        foreach (['Dashboard', 'Academic', 'Finance', 'Admissions', 'Communication', 'System'] as $group) {
            $this->assertStringContainsString("'" . $group . "' =>", $source);
        }
        foreach ([
            'admin/students.php',
            'teachers/index.php',
            'timetable/index.php',
            'campus/lesson_fees.php',
            'admin/admissions_control.php',
            'admin/communications.php',
            'admin/users.php',
            'admin/audit_log.php',
        ] as $route) {
            $this->assertStringContainsString($route, $source);
        }
    }

    public function testRailKeepsFourDotControlAndMobileTouchContract(): void
    {
        $header = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/header.php');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/app-rail.css');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/app-rail.js');

        $this->assertStringContainsString('app-rail-toggle-grid', $header);
        $this->assertStringContainsString('aria-hidden="true" inert', $header);
        $this->assertStringContainsString('min-height: 46px', $css);
        $this->assertStringContainsString('window.__ECK_APP_RAIL_BOUND__', $js);
        $this->assertStringNotContainsString('hamburger', strtolower($header));
    }
}
