<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\User::whereIn('email', ['pastor.john@example.test', 'admin@churchflow.test'])->get() as $u) {
    echo $u->email
        .' | church_id=' . ($u->church_id ?? 'null')
        .' | platform_admin=' . ($u->is_platform_admin ? 'YES' : 'no')
        .PHP_EOL;
}