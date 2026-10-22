<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The responsive dashboard shell (hamburger + off-canvas sidebar).
 *
 * WHY THIS EXISTS
 * ---------------
 * The menus were genuinely not responsive, and the cause was not markup or JS —
 * it was a STALE COMPILED STYLESHEET. The source app.css had the mobile drawer
 * rules; the shipped public/build/assets/app-*.css did not, because the build
 * predated them. Every page kept rendering the hamburger button (the markup
 * lives in the shared layout), but nothing it did was styled, so on a phone the
 * button did nothing visible.
 *
 * That failure is invisible to every other test: the HTML is correct, the
 * controllers are correct, and the only thing wrong is a file in public/build.
 * These assertions read the ACTUAL shipped CSS, so a stale or broken build is
 * caught here rather than on someone's phone.
 */
class ResponsiveShellTest extends TestCase
{
    use RefreshDatabase;

    /** The compiled stylesheet the pages actually load. */
    private function compiledCss(): ?string
    {
        $files = glob(public_path('build/assets/app-*.css'));

        if ($files === []) {
            return null;
        }

        return file_get_contents($files[0]);
    }

    public function test_the_shared_shell_renders_the_hamburger_and_drawer_markup(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('cf-navtoggle', $html, 'The hamburger button must render.');
        $this->assertStringContainsString('cf-sidebar', $html, 'The sidebar must render.');
        $this->assertStringContainsString('cf-navscrim', $html, 'The off-canvas scrim must render.');
        $this->assertStringContainsString('cf-sidebar__close', $html, 'The drawer close button must render.');
        $this->assertStringContainsString("Alpine.data('cfNav'", $html, 'The drawer behaviour must be wired.');
    }

    public function test_the_platform_admin_area_uses_the_same_responsive_shell(): void
    {
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);

        $html = $this->actingAs($admin)->get(route('platform-admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('cf-navtoggle', $html);
        $this->assertStringContainsString('cf-sidebar', $html);
        $this->assertStringContainsString('cf-navscrim', $html);
    }

    public function test_the_shipped_stylesheet_contains_the_responsive_drawer_rules(): void
    {
        $css = $this->compiledCss();

        if ($css === null) {
            $this->markTestSkipped('No compiled CSS — this environment runs from source (vite dev).');
        }

        // The breakpoint itself.
        $this->assertStringContainsString('61.99rem', $css, 'The responsive breakpoint is missing from the compiled CSS.');

        // The rules that make the hamburger and drawer actually work. A build
        // that predates these (which is exactly what shipped before) fails here.
        foreach (['cf-navtoggle', 'cf-navscrim', 'cf-sidebar'] as $selector) {
            $this->assertStringContainsString(
                $selector,
                $css,
                "The compiled CSS is missing `.{$selector}` — the stylesheet is stale. Run `npm run build`.",
            );
        }

        $this->assertStringContainsString(
            'is-open',
            $css,
            'The open state of the drawer (`.cf-sidebar.is-open`) is missing from the compiled CSS.',
        );
    }
}
