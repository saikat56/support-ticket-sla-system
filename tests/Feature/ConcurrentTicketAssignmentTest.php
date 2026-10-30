<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrentTicketAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected User $customer;
    protected User $agent1;
    protected User $agent2;
    protected TicketAssignmentService $assignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
        ]);

        $this->customer = User::create([
            'name' => 'Alice Customer',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::CUSTOMER,
        ]);

        $this->agent1 = User::create([
            'name' => 'Agent One',
            'email' => 'agent1@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::AGENT,
        ]);

        $this->agent2 = User::create([
            'name' => 'Agent Two',
            'email' => 'agent2@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::AGENT,
        ]);

        $this->assignmentService = app(TicketAssignmentService::class);
    }

    public function test_first_agent_successfully_claims_unassigned_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000101',
            'subject' => 'Server Outage',
            'description' => 'System unresponsive',
            'priority' => TicketPriority::CRITICAL,
            'status' => TicketStatus::OPEN,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => null,
        ]);

        $claimed = $this->assignmentService->claimTicket($ticket, $this->agent1);

        $this->assertEquals($this->agent1->id, $claimed->assigned_to_user_id);
        $this->assertEquals(TicketStatus::IN_PROGRESS, $claimed->status);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'event_type' => 'claimed',
            'user_id' => $this->agent1->id,
        ]);
    }

    public function test_second_agent_is_prevented_when_claiming_already_claimed_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000102',
            'subject' => 'Billing Query',
            'description' => 'Invoice clarification needed',
            'priority' => TicketPriority::MEDIUM,
            'status' => TicketStatus::OPEN,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => null,
        ]);

        // Agent 1 claims first
        $this->assignmentService->claimTicket($ticket, $this->agent1);

        // Agent 2 attempts to claim at the same time
        $this->expectException(TicketAlreadyAssignedException::class);
        $this->assignmentService->claimTicket($ticket, $this->agent2);

        // Verify ticket remains assigned exclusively to Agent 1
        $ticket->refresh();
        $this->assertEquals($this->agent1->id, $ticket->assigned_to_user_id);
    }

    public function test_automatic_assignment_picks_agent_with_least_active_workload(): void
    {
        // Give Agent 1 two active tickets
        Ticket::create([
            'ticket_number' => 'TKT-2026-000103',
            'subject' => 'Task A',
            'description' => 'Desc A',
            'priority' => TicketPriority::LOW,
            'status' => TicketStatus::IN_PROGRESS,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => $this->agent1->id,
        ]);

        Ticket::create([
            'ticket_number' => 'TKT-2026-000104',
            'subject' => 'Task B',
            'description' => 'Desc B',
            'priority' => TicketPriority::LOW,
            'status' => TicketStatus::IN_PROGRESS,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => $this->agent1->id,
        ]);

        // Agent 2 has 0 active tickets
        $newTicket = Ticket::create([
            'ticket_number' => 'TKT-2026-000105',
            'subject' => 'New Incoming Ticket',
            'description' => 'Desc C',
            'priority' => TicketPriority::HIGH,
            'status' => TicketStatus::OPEN,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer->id,
            'assigned_to_user_id' => null,
        ]);

        $assigned = $this->assignmentService->assignAutomatically($newTicket);

        // Expect Agent 2 to be selected because workload is 0 vs Agent 1's 2
        $this->assertEquals($this->agent2->id, $assigned->id);
        $this->assertEquals($this->agent2->id, $newTicket->fresh()->assigned_to_user_id);
    }
}
