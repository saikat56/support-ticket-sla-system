<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected User $customer1;
    protected User $customer2;
    protected User $agent;
    protected User $admin;
    protected Ticket $ticketCustomer1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'General Inquiry',
            'slug' => 'general-inquiry',
        ]);

        $this->customer1 = User::create([
            'name' => 'Customer One',
            'email' => 'cust1@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::CUSTOMER,
        ]);

        $this->customer2 = User::create([
            'name' => 'Customer Two',
            'email' => 'cust2@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::CUSTOMER,
        ]);

        $this->agent = User::create([
            'name' => 'Agent One',
            'email' => 'agent@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::AGENT,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
        ]);

        $this->ticketCustomer1 = Ticket::create([
            'ticket_number' => 'TKT-2026-000201',
            'subject' => 'Secret Customer Issue',
            'description' => 'Confidential details',
            'priority' => TicketPriority::HIGH,
            'status' => TicketStatus::OPEN,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->customer1->id,
        ]);
    }

    public function test_customer_can_view_own_ticket(): void
    {
        $response = $this->actingAs($this->customer1)
            ->get(route('tickets.show', $this->ticketCustomer1));

        $response->assertOk();
        $response->assertSee('Secret Customer Issue');
    }

    public function test_customer_cannot_view_other_customers_ticket(): void
    {
        $response = $this->actingAs($this->customer2)
            ->get(route('tickets.show', $this->ticketCustomer1));

        $response->assertForbidden();
    }

    public function test_agent_and_admin_can_view_any_customer_ticket(): void
    {
        $this->actingAs($this->agent)
            ->get(route('tickets.show', $this->ticketCustomer1))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('tickets.show', $this->ticketCustomer1))
            ->assertOk();
    }

    public function test_internal_notes_are_strictly_hidden_from_customers(): void
    {
        // Add public reply
        TicketReply::create([
            'ticket_id' => $this->ticketCustomer1->id,
            'user_id' => $this->agent->id,
            'message' => 'PUBLIC REPLY: We are investigating your issue.',
            'is_internal' => false,
        ]);

        // Add internal note
        TicketReply::create([
            'ticket_id' => $this->ticketCustomer1->id,
            'user_id' => $this->agent->id,
            'message' => 'INTERNAL AGENT NOTE: Customer might be affected by database bug #409.',
            'is_internal' => true,
        ]);

        // Customer views the ticket
        $customerResponse = $this->actingAs($this->customer1)
            ->get(route('tickets.show', $this->ticketCustomer1));

        $customerResponse->assertOk();
        $customerResponse->assertSee('PUBLIC REPLY: We are investigating your issue.');
        $customerResponse->assertDontSee('INTERNAL AGENT NOTE: Customer might be affected by database bug #409.');

        // Agent views the ticket
        $agentResponse = $this->actingAs($this->agent)
            ->get(route('tickets.show', $this->ticketCustomer1));

        $agentResponse->assertOk();
        $agentResponse->assertSee('PUBLIC REPLY: We are investigating your issue.');
        $agentResponse->assertSee('INTERNAL AGENT NOTE: Customer might be affected by database bug #409.');
    }

    public function test_customer_cannot_post_internal_notes(): void
    {
        $response = $this->actingAs($this->customer1)
            ->post(route('tickets.replies.store', $this->ticketCustomer1), [
                'message' => 'Sneaky internal note attempt',
                'is_internal' => 1,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('ticket_replies', [
            'message' => 'Sneaky internal note attempt',
        ]);
    }

    public function test_customer_cannot_access_csv_import_portal(): void
    {
        $this->actingAs($this->customer1)
            ->get(route('imports.index'))
            ->assertForbidden();
    }
}

