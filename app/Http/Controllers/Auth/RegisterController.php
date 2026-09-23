<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * NOTE — this is the Phase 1 registration path only: it creates a User and
 * a Church directly. From Phase 8 (SaaS billing) onward, this flow moves
 * behind the payment-gated checkout described in the architecture doc
 * (§F): a church row is only created after a verified payment, via
 * ActivateChurchAfterPayment, not from this controller. Kept intentionally
 * simple here so Phase 1's isolation test has two real tenants to compare.
 */
class RegisterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'church_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $church = Church::create([
                'name' => $data['church_name'],
                'slug' => Str::slug($data['church_name']).'-'.Str::random(6),
                'status' => 'pending', // Phase 8 flips this to 'active' post-payment
            ]);

            app()->instance('tenant.church_id', $church->id);

            $ownerRole = Role::firstOrCreate(
                ['church_id' => $church->id, 'name' => 'Church Owner'],
            );

            $user = User::create([
                'church_id' => $church->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->roles()->attach($ownerRole);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
