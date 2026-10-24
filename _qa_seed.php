<?php
// TEMPORARY QA helper — deleted after use.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$owner = \App\Models\User::withoutGlobalScopes()->where('email', 'owner@churchflow.test')->firstOrFail();
$churchId = $owner->church_id;

// Clean any previous run.
$prev = \App\Models\User::withoutGlobalScopes()->where('email', 'qa-pastor@churchflow.test')->first();
if ($prev) {
    \DB::table('role_user')->where('user_id', $prev->id)->delete();
    $prev->forceDelete();
}
\App\Models\Role::withoutGlobalScopes()
    ->where('church_id', $churchId)->where('name', 'like', 'Parish Pastor QA%')
    ->get()->each(function ($r) {
        \DB::table('permission_role')->where('role_id', $r->id)->delete();
        \DB::table('role_user')->where('role_id', $r->id)->delete();
        $r->delete();
    });
\App\Models\Role::withoutGlobalScopes()
    ->where('church_id', $churchId)->where('name', 'like', 'Pastor · %')
    ->get()->each(function ($r) {
        \DB::table('permission_role')->where('role_id', $r->id)->delete();
        \DB::table('role_user')->where('role_id', $r->id)->delete();
        $r->delete();
    });

$unit = \App\Models\OrganizationalUnit::withoutGlobalScopes()
    ->where('church_id', $churchId)->firstOrFail();

$role = \App\Models\Role::create(['church_id' => $churchId, 'name' => 'Parish Pastor QA']);
$role->permissions()->sync(
    \App\Models\Permission::whereIn('name', ['settings.manage', 'members.view', 'finance.view'])->pluck('id')
);

$pastor = \App\Models\User::create([
    'church_id' => $churchId,
    'organizational_unit_id' => $unit->id,
    'name' => 'QA Parish Pastor',
    'email' => 'qa-pastor@churchflow.test',
    'password' => \Illuminate\Support\Facades\Hash::make('Pastor@12345'),
    'is_active' => true,
    'is_registrar' => false,
]);
$pastor->roles()->attach($role->id);

echo 'church='.$churchId.' unit='.$unit->name.' pastor=#'.$pastor->id.PHP_EOL;
echo 'owner isRegistrar='.($owner->isRegistrar() ? 'yes' : 'no').PHP_EOL;
