<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\FinancialAccount;
use App\Models\Member;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Not part of the default DatabaseSeeder run — this exists purely so the
 * dashboard (and anything else that reads real data) has something to show
 * on a fresh install. Run explicitly:
 *
 *     php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
 *
 * Safe to re-run: it creates a brand-new "Grace Chapel (Demo)" church each
 * time rather than mutating an existing one, so it never collides with real
 * tenant data.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        DB::transaction(function () {
            $church = Church::create([
                'name' => 'Grace Chapel (Demo)',
                'slug' => 'grace-chapel-demo-'.Str::random(6),
                'denomination' => 'Non-denominational',
                'timezone' => 'Africa/Lagos',
                'currency' => 'NGN',
                'status' => 'active',
            ]);

            app()->instance('tenant.church_id', $church->id);

            $ownerDefaults = Role::withoutGlobalScopes()
                ->whereNull('church_id')->where('name', 'Church Owner')->first();

            $ownerRole = Role::create(['church_id' => $church->id, 'name' => 'Church Owner']);
            $ownerRole->permissions()->sync($ownerDefaults->permissions()->pluck('permissions.id'));

            $admin = User::create([
                'church_id' => $church->id,
                'name' => 'Pastor John',
                'email' => 'pastor.john@example.test',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $admin->roles()->attach($ownerRole);

            // --- Organizational units (branches) -----------------------------
            $hq = UnitType::create(['church_id' => $church->id, 'name' => 'Headquarters', 'level' => 1]);
            $mainChurch = OrganizationalUnit::create([
                'church_id' => $church->id, 'unit_type_id' => $hq->id, 'name' => 'Main Church', 'code' => 'HQ',
            ]);

            // --- Departments --------------------------------------------------
            $departments = collect(['Youth Department', 'Children Department', 'Choir Department', 'Men Fellowship'])
                ->map(fn ($name) => Department::create([
                    'church_id' => $church->id, 'organizational_unit_id' => $mainChurch->id, 'name' => $name,
                ]));

            // --- Members --------------------------------------------------
            $members = collect();
            for ($i = 1; $i <= 60; $i++) {
                $joined = Carbon::now()->subDays(random_int(1, 400));
                $member = Member::create([
                    'church_id' => $church->id,
                    'organizational_unit_id' => $mainChurch->id,
                    'full_name' => "Demo Member {$i}",
                    'email' => "member{$i}@example.test",
                    'gender' => $i % 2 === 0 ? 'female' : 'male',
                    'membership_status' => $i % 7 === 0 ? 'inactive' : 'active',
                    // Assigned explicitly to sidestep Member::nextMembershipNumber()'s
                    // documented count-then-format race (see its own docblock) when
                    // seeding many rows in one request.
                    'membership_number' => sprintf('MB-DEMO-%04d', $i),
                    'date_joined' => $joined,
                ]);
                $member->timestamps = false;
                $member->forceFill(['created_at' => $joined, 'updated_at' => $joined])->save();
                $members->push($member);
            }

            // Attach members to departments so counts have real data.
            foreach ($departments as $index => $department) {
                $department->members()->attach(
                    $members->random(min(12 + $index * 3, $members->count()))->pluck('id')
                );
            }

            // --- Financial accounts + transactions (last 6 months) --------
            $account = FinancialAccount::create([
                'church_id' => $church->id, 'name' => 'General Offering', 'type' => 'general', 'is_active' => true,
            ]);

            for ($m = 5; $m >= 0; $m--) {
                $month = Carbon::now()->subMonths($m);

                for ($t = 0; $t < random_int(6, 10); $t++) {
                    $date = $month->copy()->startOfMonth()->addDays(random_int(0, 27));
                    $tx = Transaction::create([
                        'church_id' => $church->id,
                        'financial_account_id' => $account->id,
                        'type' => 'income',
                        'category' => 'offering',
                        'amount' => random_int(80_000, 950_000),
                        'description' => 'Sunday offering',
                        'transacted_on' => $date,
                        'recorded_by' => $admin->id,
                        'approval_status' => 'not_required',
                    ]);
                    $tx->timestamps = false;
                    $tx->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
                }

                for ($t = 0; $t < random_int(3, 6); $t++) {
                    $date = $month->copy()->startOfMonth()->addDays(random_int(0, 27));
                    $tx = Transaction::create([
                        'church_id' => $church->id,
                        'financial_account_id' => $account->id,
                        'type' => 'expense',
                        'category' => 'utilities',
                        'amount' => random_int(50_000, 600_000),
                        'description' => 'Operating expense',
                        'transacted_on' => $date,
                        'recorded_by' => $admin->id,
                        'approval_status' => 'not_required',
                    ]);
                    $tx->timestamps = false;
                    $tx->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
                }
            }

            // --- Events -----------------------------------------------------
            $events = [
                ['title' => 'Sunday Service', 'starts_at' => now()->next('Sunday')->setTime(8, 0)],
                ['title' => 'Youth Conference', 'starts_at' => now()->addDays(3)->setTime(10, 0)],
                ['title' => 'Church Anniversary', 'starts_at' => now()->addDays(5)->setTime(10, 0)],
                ['title' => 'Midweek Service', 'starts_at' => now()->addDay()->setTime(18, 0)],
            ];
            foreach ($events as $e) {
                Event::create([
                    'church_id' => $church->id,
                    'organizational_unit_id' => $mainChurch->id,
                    'title' => $e['title'],
                    'venue' => 'Main Auditorium',
                    'starts_at' => $e['starts_at'],
                    'ends_at' => $e['starts_at']->copy()->addHours(2),
                    'status' => 'published',
                    'organizer_id' => $admin->id,
                ]);
            }

            // Today's schedule needs at least one event today to be non-empty.
            Event::create([
                'church_id' => $church->id,
                'organizational_unit_id' => $mainChurch->id,
                'title' => 'Prayer Meeting',
                'venue' => 'Main Auditorium',
                'starts_at' => now()->setTime(18, 0),
                'ends_at' => now()->setTime(19, 30),
                'status' => 'published',
                'organizer_id' => $admin->id,
            ]);

            // --- A few audit log entries for "Recent Activities" ------------
            foreach ($members->take(3) as $member) {
                AuditLog::create([
                    'church_id' => $church->id,
                    'user_id' => $admin->id,
                    'action' => 'member.created',
                    'auditable_type' => Member::class,
                    'auditable_id' => $member->id,
                    'new_values' => ['full_name' => $member->full_name],
                    'created_at' => now()->subHours(random_int(1, 6)),
                ]);
            }

            $this->command?->info("Demo church created: {$church->name} (login: pastor.john@example.test / password)");
        });
    }
}
