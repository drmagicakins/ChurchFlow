<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * §1-2: registration creates a USER ACCOUNT only — no church, no role, no
 * dashboard. That is the entire point of the payment-gated flow Phase 8
 * introduces: "Register → Free Church Account → Dashboard" is exactly the
 * flow §1 says NOT to build. church_id stays null (same as a platform
 * admin) until ActivateChurchFromCheckout runs after a verified payment.
 *
 * This replaces Phase 1's original version of this controller, which
 * created a church directly — that was explicitly flagged as temporary in
 * its own docblock at the time, for the same reason a foundation phase
 * needed two real tenants to test isolation against before Billing existed.
 *
 * ONE THING CARRIED FORWARD rather than dropped: the Phase 1 version had to
 * be fixed to CLONE the system-default "Church Owner" role's permissions
 * into the new church's own copy. Creating a church-scoped role by name
 * alone produces a role with no permissions, which 403s the new owner out
 * of every gated page. That clone now lives in ActivateChurchFromCheckout,
 * which is the one place a church is created — the responsibility moved
 * with the church creation, it was not lost. Locked down by
 * tests/Feature/RegistrationPermissionsTest.php.
 */
class RegisterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Scoped to church_id IS NULL on purpose: email is unique per
            // church (see the users migration), not globally, so a plain
            // unique:users,email rule would wrongly reject someone whose
            // email happens to already exist as staff inside some other
            // church. Only another not-yet-attached account (a pre-payment
            // registrant or platform admin) is a genuine collision here.
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('church_id')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('plans.index');
    }
}
