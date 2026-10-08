<?php

namespace Tests\Feature;

use App\Domains\Communication\Services\SmsWalletService;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SmsWallet/SmsTransaction are tenant-scoped like every other tenant model,
     * so a tenant must be bound before reading them back — exactly what
     * IdentifyTenant does inside a real request. Binding it in setUp keeps
     * these tests asserting wallet behaviour rather than tenant plumbing.
     */
    private function walletFor(Church $church): \App\Models\SmsWallet
    {
        app()->instance('tenant.church_id', $church->id);

        return (new SmsWalletService())->walletFor($church);
    }

    public function test_crediting_a_wallet_increases_balance_and_writes_a_ledger_row(): void
    {
        $church = Church::factory()->create();
        $service = new SmsWalletService();
        $wallet = $this->walletFor($church);

        $service->credit($wallet, 1000, 'purchase', 'payment:abc123', 'Bought 1000 credits');

        $wallet = $wallet->fresh();
        $this->assertSame(1000, $wallet->balance_units);
        $this->assertCount(1, $wallet->transactions);
        $this->assertSame('purchase', $wallet->transactions->first()->type);
        $this->assertSame(1000, $wallet->transactions->first()->balance_after);
    }

    public function test_debiting_more_than_the_balance_is_rejected_and_changes_nothing(): void
    {
        $church = Church::factory()->create();
        $service = new SmsWalletService();
        $wallet = $this->walletFor($church);
        $service->credit($wallet, 100, 'purchase');

        try {
            $service->debit($wallet->fresh(), 500, 'campaign:1');
            $this->fail('Expected an exception for insufficient credits.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // expected
        }

        $this->assertSame(100, $wallet->fresh()->balance_units, 'A rejected debit must not partially apply.');
        $this->assertCount(1, $wallet->fresh()->transactions, 'A rejected debit must not write a ledger row.');
    }

    public function test_refund_adds_units_back_with_a_reason_on_record(): void
    {
        $church = Church::factory()->create();
        $service = new SmsWalletService();
        $wallet = $this->walletFor($church);
        $service->credit($wallet, 100, 'purchase');
        $service->debit($wallet->fresh(), 50, 'campaign:1');

        $service->refund($wallet->fresh(), 10, 'campaign:1:member:5', 'Failed: invalid number');

        $wallet = $wallet->fresh();
        $this->assertSame(60, $wallet->balance_units); // 100 - 50 + 10
        $lastTransaction = $wallet->transactions()->latest()->first();
        $this->assertSame('refund', $lastTransaction->type);
        $this->assertSame('Failed: invalid number', $lastTransaction->description);
    }

    public function test_a_church_cannot_see_another_churchs_wallet_or_transactions(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $service = new SmsWalletService();

        $walletA = $this->walletFor($churchA);
        app()->instance('tenant.church_id', $churchB->id);
        $walletB = $service->walletFor($churchB);
        $service->credit($walletA, 100, 'purchase');
        $service->credit($walletB, 999, 'purchase');

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, \App\Models\SmsWallet::all());
        $this->assertSame(100, \App\Models\SmsWallet::first()->balance_units);
    }
}
