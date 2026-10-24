<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (\App\Models\User::withoutGlobalScopes()->get() as $u) {
    $roleIds = \DB::table('role_user')->where('user_id', $u->id)->pluck('role_id');
    $roleNames = $roleIds->isEmpty() ? '-' : \App\Models\Role::withoutGlobalScopes()->whereIn('id', $roleIds)->pluck('name')->implode(',');
    $permCount = \DB::table('permission_role')->whereIn('role_id', $roleIds)->distinct()->count('permission_id');
    echo '#'.$u->id.' '.$u->email.' unit='.var_export($u->organizational_unit_id, true)
        .' reg='.(int) $u->is_registrar.' roles=['.$roleNames.'] perms='.$permCount.PHP_EOL;
}
