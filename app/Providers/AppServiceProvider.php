<?php

namespace App\Providers;

use App\Models\Member;
use App\Policies\MemberPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Register one Gate::policy() line per tenant-scoped model here as
        // each domain (Finance, Subvention, Events...) lands. Member is the
        // Phase 1 example.
        Gate::policy(Member::class, MemberPolicy::class);
    }
}
