<?php

namespace Tests\Unit;

use App\Enums\TicketPriority;
use App\Enums\TicketSlaStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaCalculatorService;
use Carbon\Carbon;
use Tests\TestCase;

class SlaCalculatorTest extends TestCase
{
    protected SlaCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SlaCalculatorService();
    }

    public function test_critical_ticket_created_on_thursday_at_5pm_rolls_over_to_sunday_10am(): void
    {
        // 2026-11-05 is Thursday
        $startTime = Carbon::create(2026, 11, 5, 17, 0, 0, 'UTC');

        // Critical SLA is 2 hours
        $dueDate = $this->service->calculateDueDate(TicketPriority::CRITICAL, $startTime);

        // Expected: 2026-11-08 (Sunday) at 10:00 AM UTC
        $this->assertEquals('2026-11-08 10:00:00', $dueDate->format('Y-m-d H:i:s'));
    }

    public function test_ticket_created_on_weekend_forwards_to_sunday_morning_before_applying_sla(): void
    {
        // 2026-11-06 is Friday (weekend) at 14:00
        $startTime = Carbon::create(2026, 11, 6, 14, 0, 0, 'UTC');

        // High SLA is 8 hours
        $dueDate = $this->service->calculateDueDate(TicketPriority::HIGH, $startTime);

        // Starts on Sunday 09:00, 8 hours later => Sunday 17:00:00
        $this->assertEquals('2026-11-08 17:00:00', $dueDate->format('Y-m-d H:i:s'));
    }

    public function test_medium_ticket_spans_multiple_business_days(): void
    {
        // Sunday 2026-11-08 at 09:00:00
        $startTime = Carbon::create(2026, 11, 8, 9, 0, 0, 'UTC');

        // Medium SLA is 24 business hours
        // Day 1 (Sun): 9h (15h remain)
        // Day 2 (Mon): 9h (6h remain)
        // Day 3 (Tue): 6h => 09:00 + 6h = 15:00:00
        $dueDate = $this->service->calculateDueDate(TicketPriority::MEDIUM, $startTime);

        $this->assertEquals('2026-11-10 15:00:00', $dueDate->format('Y-m-d H:i:s'));
    }

    public function test_ticket_created_before_opening_hours_starts_at_9am(): void
    {
        // Monday 2026-11-09 at 06:30 AM
        $startTime = Carbon::create(2026, 11, 9, 6, 30, 0, 'UTC');

        // Critical SLA is 2 hours => should be due at 11:00 AM on Monday
        $dueDate = $this->service->calculateDueDate(TicketPriority::CRITICAL, $startTime);

        $this->assertEquals('2026-11-09 11:00:00', $dueDate->format('Y-m-d H:i:s'));
    }

    public function test_evaluate_sla_status_detects_breach(): void
    {
        $ticket = new Ticket([
            'priority' => TicketPriority::HIGH,
            'sla_due_at' => Carbon::create(2026, 11, 8, 12, 0, 0, 'UTC'),
        ]);

        // At 12:01 PM, ticket is breached
        $currentTime = Carbon::create(2026, 11, 8, 12, 1, 0, 'UTC');
        $status = $this->service->evaluateSlaStatus($ticket, $currentTime);

        $this->assertEquals(TicketSlaStatus::BREACHED, $status);
    }
}

