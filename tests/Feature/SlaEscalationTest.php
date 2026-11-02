<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketSlaStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketSlaApproachingNotification;
use App\Notifications\TicketSlaBreachedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected User $customer;
    protected User $agent;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
        ]);

        $this->customer = User::create([
            'name' => 'Customer Test',
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::CUSTOMER,
        ]);

        $this->agent = User::create([
            'name' => 'Support Agent',
            'email' => 'agent@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::AGENT,
        ]);

        $this->admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
        ]);
    }

    public function test_scheduler_escalates_breached_ticket_and_notifies_agent_and_admins(): void
    {
        // Create a ticket whose SLA due date was 2 hours ago
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000401',
            'subject' => 'Late ticket',
            'description' => 'Breached SLA description',
            'priority' => TicketPriority::CRITICAL,
            'status' => TicketStatus::OPEN,
            'sla_status' => TicketSlaStatus::WITHIN_SLA,
            'is_escalated' => false,
            'sla_due_at' => now()->subHours(2),
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => $this->agent->id,
        ]);

        $this->artisan('tickets:check-sla')
            ->assertSuccessful();

        $ticket->refresh();

        $this->assertEquals(TicketSlaStatus::BREACHED, $ticket->sla_status);
        $this->assertTrue($ticket->is_escalated);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'event_type' => 'escalated',
        ]);

        Notification::assertSentTo($this->agent, TicketSlaBreachedNotification::class);
        Notification::assertSentTo($this->admin, TicketSlaBreachedNotification::class);
    }

    public function test_scheduler_warns_for_tickets_approaching_sla_deadline(): void
    {
        // Critical SLA is 2 hours (120 mins). Approaching threshold is <= 60 mins or 20%
        // Place due date 30 minutes in the future
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000402',
            'subject' => 'Approaching deadline ticket',
            'description' => 'Approaching description',
            'priority' => TicketPriority::CRITICAL,
            'status' => TicketStatus::IN_PROGRESS,
            'sla_status' => TicketSlaStatus::WITHIN_SLA,
            'is_escalated' => false,
            'sla_due_at' => now()->addMinutes(30),
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => $this->agent->id,
        ]);

        $this->artisan('tickets:check-sla')
            ->assertSuccessful();

        $ticket->refresh();

        $this->assertEquals(TicketSlaStatus::APPROACHING, $ticket->sla_status);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'event_type' => 'approaching_breach',
        ]);

        Notification::assertSentTo($this->agent, TicketSlaApproachingNotification::class);
    }
}

