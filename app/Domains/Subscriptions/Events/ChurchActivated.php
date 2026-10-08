<?php

namespace App\Domains\Subscriptions\Events;

use App\Models\Church;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired once, at the end of ActivateChurchFromCheckout — Phase 9's onboarding tour hooks this. */
class ChurchActivated
{
    use Dispatchable;

    public function __construct(public readonly Church $church) {}
}
