<?php

namespace Tests\Feature;

use App\Domains\Support\Services\SupportTicketService;
use App\Models\Church;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SupportTicketService
    {
        return new SupportTicketService(new \App\Domains\Files\Services\FileUploadService());
    }

    public function test_a_church_user_can_raise_a_ticket(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        $ticket = $this->service()->create($user, [
            'subject' => 'Cannot export members',
            'category' => 'technical',
            'description' => 'The CSV export button does nothing.',
            'priority' => 'high',
        ]);

        $this->assertSame('open', $ticket->status);
        $this->assertSame($church->id, $ticket->church_id);
        $this->assertSame($user->id, $ticket->created_by);
    }

    public function test_an_internal_note_is_hidden_from_the_churchs_own_visible_replies(): void
    {
        $church = Church::factory()->create();
        $customer = User::factory()->create(['church_id' => $church->id]);
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);
        $service = $this->service();

        $ticket = $service->create($customer, [
            'subject' => 'Billing question', 'category' => 'billing', 'description' => 'Why was I charged twice?',
        ]);

        $service->reply($ticket, $admin, 'Checking the ledger internally — looks like a double webhook delivery.', true);
        $service->reply($ticket, $admin, 'Thanks for flagging — refunding the duplicate charge now.', false);

        app()->instance('tenant.church_id', $church->id);
        $visible = $ticket->fresh()->visibleReplies;
        $this->assertCount(1, $visible);
        $this->assertStringContainsString('refunding', $visible->first()->body);

        // Platform staff see both.
        app()->instance('tenant.disabled', true);
        $this->assertCount(2, $ticket->fresh()->replies);
    }

    public function test_only_platform_staff_can_post_an_internal_note(): void
    {
        $church = Church::factory()->create();
        $customer = User::factory()->create(['church_id' => $church->id]);
        $ticket = SupportTicket::factory()->for($church, 'church')->create(['created_by' => $customer->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->reply($ticket, $customer, 'trying to sneak an internal note', true);
    }

    public function test_a_support_reply_moves_an_open_ticket_to_waiting_on_customer_and_a_customer_reply_moves_it_back(): void
    {
        $church = Church::factory()->create();
        $customer = User::factory()->create(['church_id' => $church->id]);
        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);
        $service = $this->service();

        $ticket = $service->create($customer, ['subject' => 'x', 'category' => 'other', 'description' => 'x']);
        $this->assertSame('open', $ticket->status);

        $service->reply($ticket, $admin, 'Can you share a screenshot?');
        $this->assertSame('waiting_on_customer', $ticket->fresh()->status);

        $service->reply($ticket->fresh(), $customer, 'Here it is.');
        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_assigning_a_ticket_requires_a_platform_admin(): void
    {
        $church = Church::factory()->create();
        $ticket = SupportTicket::factory()->for($church, 'church')->create();
        $notAnAdmin = User::factory()->create(['church_id' => $church->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service()->assign($ticket, $notAnAdmin);
    }

    public function test_resolving_and_reopening_a_ticket(): void
    {
        $ticket = SupportTicket::factory()->create();
        $service = $this->service();

        $service->resolve($ticket);
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $service->reopen($ticket->fresh());
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_a_church_cannot_see_another_churchs_tickets(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        SupportTicket::factory()->for($churchA, 'church')->create(['subject' => 'A ticket']);
        SupportTicket::factory()->for($churchB, 'church')->create(['subject' => 'B ticket']);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, SupportTicket::all());
        $this->assertSame('A ticket', SupportTicket::first()->subject);
    }

    public function test_a_platform_admin_sees_tickets_across_every_church(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        SupportTicket::factory()->for($churchA, 'church')->create();
        SupportTicket::factory()->for($churchB, 'church')->create();

        $admin = User::factory()->create(['church_id' => null, 'is_platform_admin' => true]);

        $response = $this->actingAs($admin)->get(route('platform-admin.support.index'));

        $response->assertOk();
    }

    public function test_a_normal_church_user_cannot_reach_the_platform_admin_support_screen(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user)->get(route('platform-admin.support.index'))->assertForbidden();
    }
}
