<?php
declare(strict_types=1);

namespace Edexcel\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BottomNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        // Require the component file if not already included
        require_once dirname(__DIR__, 2) . '/includes/homepage-components.php';
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testRendersFloatingBottomNavContainerWithAttributes(): void
    {
        ob_start();
        homepage_render_bottom_nav('home');
        $html = ob_get_clean();

        $this->assertStringContainsString('class="app-bottom-nav l1-capsule"', $html);
        $this->assertStringContainsString('data-bottom-nav', $html);
        $this->assertStringContainsString('data-l1-capsule', $html);
        $this->assertStringContainsString('aria-label="Quick navigation"', $html);
    }

    public function testRendersAllSixNavigationDestinations(): void
    {
        ob_start();
        homepage_render_bottom_nav('home');
        $html = ob_get_clean();

        $this->assertStringContainsString('data-cap="home"', $html);
        $this->assertStringContainsString('data-cap="intro"', $html);
        $this->assertStringContainsString('data-cap="programmes"', $html);
        $this->assertStringContainsString('data-cap="teachers"', $html);
        $this->assertStringContainsString('data-cap="faq"', $html);
        $this->assertStringContainsString('data-cap="login"', $html);

        // Icons matching Font Awesome
        $this->assertStringContainsString('fas fa-house', $html);
        $this->assertStringContainsString('fas fa-chart-line', $html);
        $this->assertStringContainsString('fas fa-book-open', $html);
        $this->assertStringContainsString('fas fa-chalkboard-user', $html);
        $this->assertStringContainsString('fas fa-circle-question', $html);
        $this->assertStringContainsString('fas fa-user', $html);

        // Labels
        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('Analytics', $html);
        $this->assertStringContainsString('Courses', $html);
        $this->assertStringContainsString('Classes', $html);
        $this->assertStringContainsString('Help', $html);
        $this->assertStringContainsString('Profile', $html);
    }

    public function testActiveItemReceivesIsActiveClassAndAriaCurrent(): void
    {
        ob_start();
        homepage_render_bottom_nav('programmes');
        $html = ob_get_clean();

        // programmes should have is-active and aria-current
        $this->assertMatchesRegularExpression('/<a[^>]+class="[^"]*\bis-active\b[^"]*"[^>]+data-cap="programmes"|<a[^>]+data-cap="programmes"[^>]+class="[^"]*\bis-active\b[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/data-cap="programmes"[^>]*aria-current="page"|aria-current="page"[^>]*data-cap="programmes"/', $html);

        // home should NOT be active
        $this->assertDoesNotMatchRegularExpression('/<a[^>]+class="[^"]*\bis-active\b[^"]*"[^>]+data-cap="home"|<a[^>]+data-cap="home"[^>]+class="[^"]*\bis-active\b[^"]*"/', $html);
    }

    public function testUnauthenticatedStateRendersLoginAuthTrigger(): void
    {
        $_SESSION = [];

        ob_start();
        homepage_render_bottom_nav('home');
        $html = ob_get_clean();

        $this->assertStringContainsString('type="button" data-open-auth="login"', $html);
        $this->assertStringContainsString('Profile', $html);
    }

    public function testAuthenticatedStudentRendersDashboardLink(): void
    {
        $_SESSION['user_id'] = 42;
        $_SESSION['role'] = 'student';

        ob_start();
        homepage_render_bottom_nav('home');
        $html = ob_get_clean();

        $this->assertStringContainsString('student/dashboard.php', $html);
        $this->assertStringContainsString('Account', $html);
        $this->assertStringNotContainsString('data-open-auth="login"', $html);
    }

    public function testAuthenticatedTeacherRendersTeacherPortalLink(): void
    {
        $_SESSION['user_id'] = 99;
        $_SESSION['role'] = 'teacher';

        ob_start();
        homepage_render_bottom_nav('home');
        $html = ob_get_clean();

        $this->assertStringContainsString('teachers/index.php', $html);
        $this->assertStringContainsString('Account', $html);
    }

    public function testCssFileContainsResponsiveAndDesignRules(): void
    {
        $cssPath = dirname(__DIR__, 2) . '/assets/css/bottom-nav.css';
        $this->assertFileExists($cssPath);

        $css = file_get_contents($cssPath);
        $this->assertStringContainsString('--bnav-bg: #161618;', $css);
        $this->assertStringContainsString('--bnav-active-bg: #2f2f34;', $css);
        $this->assertStringContainsString('.app-bottom-nav', $css);
        $this->assertStringContainsString('.app-bottom-nav-item.is-active', $css);
        $this->assertStringContainsString('@media (max-width: 992px)', $css);
        $this->assertStringContainsString('@media (max-width: 600px)', $css);
        $this->assertStringContainsString('@media (min-width: 993px)', $css);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $css);
    }
}
