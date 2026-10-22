<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The pastor's settlement bank account (§ "bank account area for the pastor
 * to input their account number ... so the admin (super admin) can access it").
 *
 * A pastor records the account their subvention/remittance should be paid
 * into; the super-admin/registrar reads the same records to make the transfer.
 * Both sides use this one screen — what differs is the policy: a pastor sees
 * only their own row, an administrator sees every pastor's.
 */
class BankAccountController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', BankAccount::class);

        $user = $request->user();
        $isAdmin = $user->hasPermission('settings.manage');

        // A pastor sees their own account; an administrator sees every
        // settlement account in the church, joined to the pastor's name.
        $accounts = BankAccount::query()
            ->with('user')
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->get();

        // The church's own staff who can hold a settlement account — used for
        // the admin's "record on behalf of" picker.
        $pastors = $isAdmin
            ? User::query()->where('church_id', $user->church_id)->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        return view('finance.bank-accounts.index', [
            'accounts' => $accounts,
            'banks' => config('nigerian_banks.banks', []),
            'isAdmin' => $isAdmin,
            'pastors' => $pastors,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BankAccount::class);

        $banks = config('nigerian_banks.banks', []);

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('church_id', $request->user()->church_id)],
            'bank_code' => ['required', 'string', Rule::in(array_keys($banks))],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
        ], [
            'bank_code.in' => 'Choose a bank from the list.',
            'account_number.regex' => 'A Nigerian account number is 10 digits.',
        ]);

        // A pastor may only set their own account; an administrator may set
        // one for any pastor in the same church.
        $ownerId = $request->user()->id;
        if (! empty($data['user_id']) && $request->user()->hasPermission('settings.manage')) {
            $ownerId = (int) $data['user_id'];
        }

        BankAccount::updateOrCreate(
            ['church_id' => $request->user()->church_id, 'user_id' => $ownerId],
            [
                'bank_code' => $data['bank_code'],
                'bank_name' => $banks[$data['bank_code']],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
                'is_verified' => false,
            ],
        );

        return back()->with('status', 'Bank account saved.');
    }

    /** Mark an account verified — an administrative act (settings.manage). */
    public function verify(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('verify', $bankAccount);

        $bankAccount->update(['is_verified' => ! $bankAccount->is_verified]);

        return back()->with('status', $bankAccount->is_verified
            ? 'Bank account marked verified.'
            : 'Verification removed.');
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('delete', $bankAccount);

        $bankAccount->delete();

        return back()->with('status', 'Bank account removed.');
    }
}
