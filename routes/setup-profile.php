<?php

// §11/§34: the church_profile setup step's required input.
//
// This lives in its own file, and is required from routes/web.php inside the
// auth + IdentifyTenant + subscription group, so the setup step can actually
// save the church's name. It was added here rather than inline because
// routes/web.php is held open with a user-mapped section by the local
// PHP server, which makes in-place edits of that one file fail; requiring a
// small, deliberately-scoped file keeps that file's content unchanged and the
// change reviewable on its own.
//
// The route NAME (`setup.profile`) is what the view and tests use, so its
// location does not leak into any caller.

use App\Http\Controllers\SetupWizardController;
use Illuminate\Support\Facades\Route;

Route::post('/setup/profile', [SetupWizardController::class, 'completeProfile'])
    ->name('setup.profile');
