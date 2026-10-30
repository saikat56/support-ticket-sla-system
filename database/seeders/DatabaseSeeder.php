<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Enums\TicketSlaStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\SlaCalculatorService;
use App\Services\TicketIdGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $slaCalculator = new SlaCalculatorService();
        $idGenerator = new TicketIdGenerator();

        // 1. Seed Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'is_active' => true,
            ]
        );

        $agent1 = User::firstOrCreate(
            ['email' => 'agent1@example.com'],
            [
                'name' => 'Sarah Connor (Senior Agent)',
                'password' => Hash::make('password'),
                'role' => UserRole::AGENT,
                'is_active' => true,
                'last_assigned_at' => now()->subHours(2),
            ]
        );

        $agent2 = User::firstOrCreate(
            ['email' => 'agent2@example.com'],
            [
                'name' => 'John Miller (Support Agent)',
                'password' => Hash::make('password'),
                'role' => UserRole::AGENT,
                'is_active' => true,
                'last_assigned_at' => now()->subHours(5),
            ]
        );

        $agent3 = User::firstOrCreate(
            ['email' => 'agent3@example.com'],
            [
                'name' => 'Elena Rostova (Support Agent)',
                'password' => Hash::make('password'),
                'role' => UserRole::AGENT,
                'is_active' => true,
                'last_assigned_at' => now()->subHours(10),
            ]
        );

        $customer1 = User::firstOrCreate(
            ['email' => 'customer1@example.com'],
            [
                'name' => 'Acme Corp (Alex Vance)',
                'password' => Hash::make('password'),
                'role' => UserRole::CUSTOMER,
                'is_active' => true,
            ]
        );

        $customer2 = User::firstOrCreate(
            ['email' => 'customer2@example.com'],
            [
                'name' => 'Globex Corp (David King)',
                'password' => Hash::make('password'),
                'role' => UserRole::CUSTOMER,
                'is_active' => true,
            ]
        );

        // 2. Seed Categories
        $categories = [
            ['name' => 'Technical Support', 'slug' => 'technical-support', 'description' => 'Server, API, and platform integration inquiries.'],
            ['name' => 'Billing & Invoicing', 'slug' => 'billing-invoicing', 'description' => 'Inquiries related to payments, subscriptions, and receipts.'],
            ['name' => 'Bug Report', 'slug' => 'bug-report', 'description' => 'Defects, unexpected behavior, and errors.'],
            ['name' => 'Feature Request', 'slug' => 'feature-request', 'description' => 'Suggestions and enhancements for future releases.'],
            ['name' => 'General Inquiry', 'slug' => 'general-inquiry', 'description' => 'General account and service questions.'],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[$cat['slug']] = Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 3. Seed Diverse Tickets

        // Ticket 1: Critical - Breached & Escalated
        $t1CreatedAt = now()->subDays(3);
        $t1SlaDue = $slaCalculator->calculateDueDate(TicketPriority::CRITICAL, $t1CreatedAt);
        $ticket1 = Ticket::create([
            'ticket_number' => $idGenerator->generate(),
            'subject' => 'Production Database Connectivity Timeout in EU Region',
            'description' => 'Our production applications are unable to connect to the EU cluster. Latency has spiked to 12,000ms and connection pool is exhausted.',
            'priority' => TicketPriority::CRITICAL,
            'status' => TicketStatus::IN_PROGRESS,
            'sla_status' => TicketSlaStatus::BREACHED,
            'is_escalated' => true,
            'sla_due_at' => $t1SlaDue,
            'category_id' => $categoryModels['technical-support']->id,
            'created_by_user_id' => $customer1->id,
            'assigned_to_user_id' => $agent1->id,
            'created_at' => $t1CreatedAt,
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $customer1->id,
            'event_type' => 'created',
            'description' => 'Ticket created by Alex Vance',
            'created_at' => $t1CreatedAt,
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $agent1->id,
            'event_type' => 'claimed',
            'description' => 'Ticket claimed by Sarah Connor',
            'created_at' => $t1CreatedAt->copy()->addMinutes(15),
        ]);

        TicketReply::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $agent1->id,
            'message' => 'Investigating network routes to EU cluster now. Tracing core gateway logs.',
            'is_internal' => false,
            'created_at' => $t1CreatedAt->copy()->addMinutes(20),
        ]);

        TicketReply::create([
            'ticket_id' => $ticket1->id,
            'user_id' => $agent1->id,
            'message' => 'INTERNAL NOTE: Database replica failover triggered automatically. Need Devops lead to verify replica health.',
            'is_internal' => true,
            'created_at' => $t1CreatedAt->copy()->addMinutes(25),
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket1->id,
            'user_id' => null,
            'event_type' => 'escalated',
            'description' => 'Ticket breached resolution SLA (2 hours) and was automatically escalated to management.',
            'created_at' => $t1SlaDue->copy()->addMinute(),
        ]);

        // Ticket 2: High - Approaching SLA Breach
        $t2CreatedAt = now()->subHours(6);
        $t2SlaDue = $slaCalculator->calculateDueDate(TicketPriority::HIGH, $t2CreatedAt);
        $ticket2 = Ticket::create([
            'ticket_number' => $idGenerator->generate(),
            'subject' => 'Stripe Webhook Signature Verification Failing',
            'description' => 'Webhook events for invoice.payment_succeeded are throwing 400 Bad Request signature verification failures since the last deployment.',
            'priority' => TicketPriority::HIGH,
            'status' => TicketStatus::IN_PROGRESS,
            'sla_status' => TicketSlaStatus::APPROACHING,
            'is_escalated' => false,
            'sla_due_at' => $t2SlaDue,
            'category_id' => $categoryModels['billing-invoicing']->id,
            'created_by_user_id' => $customer2->id,
            'assigned_to_user_id' => $agent2->id,
            'created_at' => $t2CreatedAt,
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket2->id,
            'user_id' => $customer2->id,
            'event_type' => 'created',
            'description' => 'Ticket created by David King',
            'created_at' => $t2CreatedAt,
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket2->id,
            'user_id' => $admin->id,
            'event_type' => 'assigned',
            'description' => 'Assigned to John Miller by System Administrator',
            'properties' => ['assigned_to' => $agent2->name],
            'created_at' => $t2CreatedAt->copy()->addMinutes(10),
        ]);

        // Ticket 3: Medium - Open & Unassigned
        $t3CreatedAt = now()->subHours(1);
        $t3SlaDue = $slaCalculator->calculateDueDate(TicketPriority::MEDIUM, $t3CreatedAt);
        $ticket3 = Ticket::create([
            'ticket_number' => $idGenerator->generate(),
            'subject' => 'Unable to update company billing address in portal',
            'description' => 'When submitting the updated VAT registration address, the form errors with invalid zip code formatting for UK addresses.',
            'priority' => TicketPriority::MEDIUM,
            'status' => TicketStatus::OPEN,
            'sla_status' => TicketSlaStatus::WITHIN_SLA,
            'is_escalated' => false,
            'sla_due_at' => $t3SlaDue,
            'category_id' => $categoryModels['billing-invoicing']->id,
            'created_by_user_id' => $customer1->id,
            'assigned_to_user_id' => null,
            'created_at' => $t3CreatedAt,
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket3->id,
            'user_id' => $customer1->id,
            'event_type' => 'created',
            'description' => 'Ticket created by Alex Vance',
            'created_at' => $t3CreatedAt,
        ]);

        // Ticket 4: Low - Resolved
        $t4CreatedAt = now()->subDays(2);
        $t4SlaDue = $slaCalculator->calculateDueDate(TicketPriority::LOW, $t4CreatedAt);
        $t4ResolvedAt = $t4CreatedAt->copy()->addHours(14);
        $ticket4 = Ticket::create([
            'ticket_number' => $idGenerator->generate(),
            'subject' => 'Dark mode request for ticket activity log',
            'description' => 'Would love to see a dark theme toggle in the upcoming release.',
            'priority' => TicketPriority::LOW,
            'status' => TicketStatus::RESOLVED,
            'sla_status' => TicketSlaStatus::WITHIN_SLA,
            'is_escalated' => false,
            'sla_due_at' => $t4SlaDue,
            'resolved_at' => $t4ResolvedAt,
            'category_id' => $categoryModels['feature-request']->id,
            'created_by_user_id' => $customer2->id,
            'assigned_to_user_id' => $agent3->id,
            'created_at' => $t4CreatedAt,
        ]);

        TicketActivity::create([
            'ticket_id' => $ticket4->id,
            'user_id' => $agent3->id,
            'event_type' => 'status_changed',
            'description' => 'Status changed from In Progress to Resolved by Elena Rostova',
            'created_at' => $t4ResolvedAt,
        ]);

        // Ticket 5: Critical - Waiting for Customer
        $t5CreatedAt = now()->subHours(2);
        $t5SlaDue = $slaCalculator->calculateDueDate(TicketPriority::CRITICAL, $t5CreatedAt);
        $ticket5 = Ticket::create([
            'ticket_number' => $idGenerator->generate(),
            'subject' => 'SSL Certificate expiration alert on API gateway',
            'description' => 'Our monitoring reports our custom domain certificate expires in 24 hours.',
            'priority' => TicketPriority::CRITICAL,
            'status' => TicketStatus::WAITING_CUSTOMER,
            'sla_status' => TicketSlaStatus::WITHIN_SLA,
            'is_escalated' => false,
            'sla_due_at' => $t5SlaDue,
            'category_id' => $categoryModels['technical-support']->id,
            'created_by_user_id' => $customer1->id,
            'assigned_to_user_id' => $agent1->id,
            'created_at' => $t5CreatedAt,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket5->id,
            'user_id' => $agent1->id,
            'message' => 'Please provide the DNS CNAME verification record so we can issue the Let\'s Encrypt wildcard certificate.',
            'is_internal' => false,
            'created_at' => $t5CreatedAt->copy()->addMinutes(30),
        ]);
    }
}
