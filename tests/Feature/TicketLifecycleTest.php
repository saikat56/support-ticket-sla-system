<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected User $customer;
    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->category = Category::create([
            'name' => 'Bug Report',
            'slug' => 'bug-report',
        ]);

        $this->customer = User::create([
            'name' => 'Charlie Customer',
            'email' => 'charlie@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::CUSTOMER,
        ]);

        $this->agent = User::create([
            'name' => 'Dana Agent',
            'email' => 'dana@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::AGENT,
        ]);
    }

    public function test_customer_can_create_ticket_with_attachment_and_receive_notification(): void
    {
        $file = UploadedFile::fake()->create('error_trace.log', 100, 'text/plain');

        $response = $this->actingAs($this->customer)
            ->post(route('tickets.store'), [
                'subject' => 'Payment Gateway Timeout',
                'category_id' => $this->category->id,
                'priority' => TicketPriority::HIGH->value,
                'description' => 'The payment gateway timed out on checkout.',
                'attachments' => [$file],
            ]);

        $ticket = Ticket::first();

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);
        $this->assertEquals(TicketPriority::HIGH, $ticket->priority);
        $this->assertNotNull($ticket->sla_due_at);
        $this->assertCount(1, $ticket->attachments);

        Notification::assertSentTo($this->customer, TicketCreatedNotification::class);
    }

    public function test_agent_can_resolve_ticket_and_notify_customer(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000301',
            'subject' => 'Issue to resolve',
            'description' => 'Description',
            'priority' => TicketPriority::MEDIUM,
            'status' => TicketStatus::IN_PROGRESS,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => $this->agent->id,
        ]);

        $this->actingAs($this->agent)
            ->patch(route('tickets.update-status', $ticket), [
                'status' => TicketStatus::RESOLVED->value,
            ]);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::RESOLVED, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        Notification::assertSentTo($this->customer, TicketResolvedNotification::class);
    }

    public function test_customer_reply_to_resolved_ticket_reopens_it_to_in_progress(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000302',
            'subject' => 'Fixed bug that regressed',
            'description' => 'Description',
            'priority' => TicketPriority::MEDIUM,
            'status' => TicketStatus::RESOLVED,
            'resolved_at' => now()->subDay(),
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => $this->agent->id,
        ]);

        $this->actingAs($this->customer)
            ->post(route('tickets.replies.store', $ticket), [
                'message' => 'The issue actually still happens on mobile!',
            ]);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::IN_PROGRESS, $ticket->status);
    }

    public function test_priority_change_recalculates_sla_and_records_audit_history(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000303',
            'subject' => 'Escalate to critical',
            'description' => 'Description',
            'priority' => TicketPriority::LOW,
            'status' => TicketStatus::IN_PROGRESS,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => $this->agent->id,
            'created_at' => now(),
        ]);

        $this->actingAs($this->agent)
            ->patch(route('tickets.update-priority', $ticket), [
                'priority' => TicketPriority::CRITICAL->value,
            ]);

        $ticket->refresh();
        $this->assertEquals(TicketPriority::CRITICAL, $ticket->priority);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'event_type' => 'priority_changed',
            'user_id' => $this->agent->id,
        ]);
    }
}
