<?php
// TEMPORARY QA helper — deleted after use.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pastor = App\Models\User::withoutGlobalScopes()->where('email', 'qa-pastor@churchflow.test')->firstOrFail();
app()->instance('tenant.church_id', $pastor->church_id);

echo 'pastor #'.$pastor->id.' church='.$pastor->church_id.PHP_EOL;
$ids = DB::table('role_user')->where('user_id', $pastor->id)->pluck('role_id');
echo 'attached role ids: '.$ids->implode(', ').PHP_EOL;

foreach (App\Models\Role::withoutGlobalScopes()->whereIn('id', $ids)->get() as $r) {
    $names = DB::table('permission_role')
        ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
        ->where('role_id', $r->id)->pluck('permissions.name')->all();
    echo '  role #'.$r->id.' "'.$r->name.'" -> '.count($names).' perms: '.implode(', ', $names).PHP_EOL;
}

echo 'User::roles() resolves: '.$pastor->roles()->pluck('roles.id')->implode(', ').PHP_EOL;
$perms = $pastor->fresh()->load('roles.permissions')->roles->flatMap->permissions->pluck('name')->unique()->values()->all();
echo 'union: '.count($perms).' -> '.implode(', ', $perms).PHP_EOL;
