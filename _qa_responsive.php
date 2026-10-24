<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Church;
use App\Models\User;

/*
 * Inspect the RESPONSIVE shell markup and CSS directly, without a browser.
 *
 * The question is mechanical: does each dashboard page (a) render the hamburger
 * button, the off-canvas sidebar and the scrim, and (b) does the stylesheet that
 * ships carry the @media rule that shows them below the breakpoint? Those are
 * facts about the HTML and the CSS, and they can be read exactly.
 */
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$render = function (string $url, ?User $user) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'GET');
    if ($user) {
        $request->setUserResolver(fn () => $user);
    }
    try {
        $session = $kernel->getApplication()->make('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $response = $kernel->handle($request);
        return ['status' => $response->getStatusCode(), 'html' => $response->getContent()];
    } catch (\Throwable $e) {
        return ['status' => 'EXC', 'html' => '', 'error' => $e->getMessage()];
    }
};

$checks = [
    'hamburger button' => 'cf-navtoggle',
    'off-canvas sidebar' => 'cf-sidebar',
    'scrim' => 'cf-navscrim',
    'drawer close button' => 'cf-sidebar__close',
    'cfNav Alpine component' => 'Alpine.data(\'cfNav\'',
];

$owner = User::where('email', 'owner@churchflow.test')->first();
$admin = User::where('email', 'admin@churchflow.test')->first();

foreach (['USER dashboard (/dashboard)' => [$owner, '/dashboard'],
          'PLATFORM ADMIN (/platform-admin)' => [$admin, '/platform-admin']] as $label => [$user, $url]) {

    $result = $render($url, $user);
    echo '=== '.$label.' -> status '.$result['status'].' ==='.PHP_EOL;

    if ($result['status'] !== 200) {
        echo '  (not 200 — skipping markup checks)'.PHP_EOL.PHP_EOL;
        continue;
    }

    // The shell markup the CSS targets.
    foreach ($checks as $name => $needle) {
        echo '  '.str_pad($name, 26).': '.(str_contains($result['html'], $needle) ? 'present' : 'MISSING').PHP_EOL;
    }

    echo PHP_EOL;
}

// Now the stylesheet: does the compiled app.css carry the responsive rules?
$cssPath = public_path('build/assets');
$cssFiles = glob($cssPath.'/app-*.css');
echo '=== COMPILED CSS ==='.PHP_EOL;

if ($cssFiles === []) {
    echo '  no compiled app-*.css found'.PHP_EOL;
} else {
    $css = file_get_contents($cssFiles[0]);
    echo '  file: '.basename($cssFiles[0]).' ('.number_format(strlen($css)).' bytes)'.PHP_EOL;
    echo '  @media (max-width: 61.99rem)  : '.(str_contains($css, 'max-width:61.99rem') || str_contains($css, 'max-width: 61.99rem') ? 'present' : 'MISSING').PHP_EOL;
    echo '  .cf-navtoggle rule            : '.(str_contains($css, 'cf-navtoggle') ? 'present' : 'MISSING').PHP_EOL;
    echo '  .cf-sidebar.is-open rule      : '.(str_contains($css, 'cf-sidebar.is-open') ? 'present' : 'MISSING').PHP_EOL;
    echo '  .cf-navscrim rule             : '.(str_contains($css, 'cf-navscrim') ? 'present' : 'MISSING').PHP_EOL;
}
