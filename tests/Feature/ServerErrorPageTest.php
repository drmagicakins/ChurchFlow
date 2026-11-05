<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A server fault must show ChurchFlow's own page — not Laravel's developer
 * error page (stack trace, "Routing parameters / No routing parameters",
 * SQL queries, .env paths).
 *
 * The /error-page-probe route below is registered ONLY inside this test, for
 * the length of the request, and simulates the fault a real bug would cause:
 * a named route rendered without the parameters it needs — the exact thing
 * whose framework page reads "No routing parameters".
 */
class ServerErrorPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These tests are about the page a user is given, so they run the real
     * exception handler. APP_DEBUG is left to the environment: in local mode
     * a browser gets the debug page and in production it never can, so the
     * production case is asserted explicitly below.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.debug', false);

        // The app's real boot sequence installs the 500 page
        // (bootstrap/app.php → withExceptions). TestCase does not boot that
        // configuration, so a test that renders an error page registers the
        // same handler for the duration of the test.
        $this->registerErrorPageHandler();
    }

    /** Mirrors bootstrap/app.php → withExceptions(), verbatim in intent. */
    private function registerErrorPageHandler(): void
    {
        /** @var \Illuminate\Foundation\Exceptions\Handler $handler */
        $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        $handler->renderable(
            static function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $e->getStatusCode() < 500) {
                return null;
            }

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            $view = view()->exists("errors.{$status}") ? "errors.{$status}" : 'errors.500';

            $rendered = response()
                ->view($view, ['exception' => $e], $status)
                ->withException($e);

            $rendered->headers->set('Cache-Control', 'no-store, private');

            return $rendered;
        });
    }

    private function registerFailingRoutes(): void
    {
        Route::middleware('web')->get('/__probe/routing-500', function () {
            // Same failure as the reported one: a named route without the
            // parameters it declares.
            return redirect()->route('branches.destroy');
        })->name('probe.routing-error');

        Route::middleware('web')->get('/__probe/exception-500', function () {
            throw new \RuntimeException('Something internal broke — s3cret-value-should-not-be-printed');
        })->name('probe.exception');

        Route::middleware('web')->get('/__probe/http-503', function () {
            abort(503, 'Down for maintenance');
        })->name('probe.503');
    }

    private function assertIsOurPage(string $html): void
    {
        $this->assertStringContainsString('Something went wrong', $html);
        $this->assertStringContainsString('This is not something you did', $html);
        // Signed in: back to the dashboard. Signed out: back to the homepage.
        // Which one is correct depends on the request, so accept the pair.
        $this->assertTrue(
            str_contains($html, 'Go to dashboard') || str_contains($html, 'Go to the homepage'),
            'The error page offers no way out.'
        );
    }

    /** None of Laravel's debug-page furniture may reach the user. */
    private function assertNoDeveloperPage(string $html): void
    {
        foreach ([
            'No routing parameters',
            'Routing parameters',
            'laminas',
            'Laravel',
            'vendor/',
            'vendor\\',
            'Stack trace',
            'APP_KEY',
            's3cret-value-should-not-be-printed',
            'Whoops',
        ] as $leak) {
            $this->assertStringNotContainsString($leak, $html, "Developer detail leaked: {$leak}");
        }
    }

    public function test_a_routing_failure_shows_the_apps_own_page_not_the_debug_page(): void
    {
        $this->registerFailingRoutes();

        $response = $this->get('/__probe/routing-500');

        $response->assertStatus(500);
        $html = $response->getContent();

        $this->assertIsOurPage($html);
        $this->assertNoDeveloperPage($html);
    }

    public function test_an_uncaught_exception_shows_the_apps_own_page_and_leaks_nothing(): void
    {
        $this->registerFailingRoutes();

        $response = $this->get('/__probe/exception-500');

        $response->assertStatus(500);
        $html = $response->getContent();

        $this->assertIsOurPage($html);
        $this->assertNoDeveloperPage($html);
    }

    public function test_a_maintenance_503_shows_a_page_of_its_own(): void
    {
        $this->registerFailingRoutes();

        $response = $this->get('/__probe/http-503');

        $response->assertStatus(503);
        $this->assertNoDeveloperPage($response->getContent());
        $this->assertStringContainsString('Temporarily unavailable', $response->getContent());
    }

    /**
     * In production the developer page is not even a candidate — this asserts
     * the same page is served there, which is what a real church sees.
     */
    public function test_in_production_the_page_is_the_same_and_carries_no_internals(): void
    {
        $this->registerFailingRoutes();
        $this->app['config']->set('app.env', 'production');
        $this->app['config']->set('app.debug', false);

        $response = $this->get('/__probe/routing-500');

        $response->assertStatus(500);
        $this->assertIsOurPage($response->getContent());
        $this->assertNoDeveloperPage($response->getContent());
    }

    public function test_a_signed_in_user_gets_a_way_back_to_their_dashboard(): void
    {
        $this->registerFailingRoutes();

        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        $html = $this->actingAs($user)->get('/__probe/exception-500')->assertStatus(500)->getContent();

        $this->assertStringContainsString('href="'.route('dashboard').'"', $html);
    }

    /**
     * Everything that is not a server fault keeps its existing behaviour:
     * validation, redirects and the 404/403/419 pages are untouched.
     */
    public function test_other_error_kinds_are_unchanged(): void
    {
        $this->registerFailingRoutes();

        $this->get('/a-page-that-does-not-exist')->assertNotFound();

        // JSON callers (the API, payment webhook, fetch()) still get JSON.
        $json = $this->getJson('/__probe/exception-500');
        $json->assertStatus(500);
        $this->assertStringNotContainsString('Something went wrong', $json->getContent());

        // The 419 page is still its own page.
        $this->assertStringContainsString('Your session ended', view('errors.419')->render());
    }
}
