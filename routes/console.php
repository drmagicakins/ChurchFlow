<?php

// --- Phase 8 addition: merge into routes/console.php, do not overwrite ---
// (Laravel 11+ registers the scheduler here rather than in a Kernel class;
// if this project predates that, put the same line in
// App\Console\Kernel::schedule() instead.)

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:process-billing-cycle')->daily();
