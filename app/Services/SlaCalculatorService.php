<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketSlaStatus;
use App\Models\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SlaCalculatorService
{
    protected array $businessDays;
    protected int $startHour;
    protected int $startMinute;
    protected int $endHour;
    protected int $endMinute;
    protected array $holidays;

    public function __construct()
    {
        $this->businessDays = config('sla.business_days', [0, 1, 2, 3, 4]); // 0=Sunday, 4=Thursday
        $this->holidays = config('sla.holidays', []);

        [$this->startHour, $this->startMinute] = explode(':', config('sla.business_start', '09:00'));
        [$this->endHour, $this->endMinute] = explode(':', config('sla.business_end', '18:00'));
        $this->startHour = (int) $this->startHour;
        $this->startMinute = (int) $this->startMinute;
        $this->endHour = (int) $this->endHour;
        $this->endMinute = (int) $this->endMinute;
    }

    /**
     * Get the SLA duration in hours for a given priority.
     */
    public function getSlaHours(TicketPriority|string $priority): int
    {
        $key = $priority instanceof TicketPriority ? $priority->value : $priority;
        return (int) config("sla.priorities.{$key}", 24);
    }

    /**
     * Calculate the exact SLA deadline considering business hours and days.
     *
     * @param TicketPriority|string $priority
     * @param Carbon|null $startTime
     * @return Carbon
     */
    public function calculateDueDate(TicketPriority|string $priority, ?Carbon $startTime = null): Carbon
    {
        $hours = $this->getSlaHours($priority);
        $remainingMinutes = $hours * 60;

        $current = $startTime ? $startTime->copy() : now();

        // 1. If start time falls outside business hours, forward to the next business opening window
        $current = $this->forwardToBusinessStart($current);

        // 2. Consume business minutes day-by-day
        while ($remainingMinutes > 0) {
            $endOfDay = $current->copy()->setTime($this->endHour, $this->endMinute, 0);

            // Available working minutes in the current business day
            $minutesAvailableToday = $current->diffInMinutes($endOfDay, false);

            if ($remainingMinutes <= $minutesAvailableToday) {
                // Deadline fits inside today's remaining business hours
                return $current->addMinutes($remainingMinutes);
            }

            // Consume all available minutes today and roll to the next business day start
            $remainingMinutes -= $minutesAvailableToday;
            $current = $this->getNextBusinessDayStart($current);
        }

        return $current;
    }

    /**
     * Determine the SLA status of a ticket at a given point in time (default: now).
     */
    public function evaluateSlaStatus(Ticket $ticket, ?Carbon $now = null): TicketSlaStatus
    {
        $now = $now ? $now->copy() : now();

        if (!$ticket->sla_due_at) {
            return TicketSlaStatus::WITHIN_SLA;
        }

        // If ticket is already resolved, evaluate against resolution timestamp
        if ($ticket->resolved_at) {
            return $ticket->resolved_at->gt($ticket->sla_due_at)
                ? TicketSlaStatus::BREACHED
                : TicketSlaStatus::WITHIN_SLA;
        }

        // Active ticket: check if already breached
        if ($now->gte($ticket->sla_due_at)) {
            return TicketSlaStatus::BREACHED;
        }

        // Check if approaching breach
        $totalSlaMinutes = $this->getSlaHours($ticket->priority) * 60;
        $minutesUntilDue = $now->diffInMinutes($ticket->sla_due_at, false);

        $approachingPercentage = config('sla.approaching_percentage', 20);
        $approachingMinutes = config('sla.approaching_minutes', 60);

        $thresholdMinutes = max($approachingMinutes, (int) round($totalSlaMinutes * ($approachingPercentage / 100)));

        if ($minutesUntilDue <= $thresholdMinutes) {
            return TicketSlaStatus::APPROACHING;
        }

        return TicketSlaStatus::WITHIN_SLA;
    }

    /**
     * Checks if a given timestamp falls within business days and hours.
     */
    public function isBusinessTime(Carbon $time): bool
    {
        if (! $this->isBusinessDay($time)) {
            return false;
        }

        $start = $time->copy()->setTime($this->startHour, $this->startMinute, 0);
        $end = $time->copy()->setTime($this->endHour, $this->endMinute, 0);

        return $time->gte($start) && $time->lt($end);
    }

    /**
     * Checks if a date is a designated business day and not a holiday.
     */
    public function isBusinessDay(Carbon $date): bool
    {
        $dayOfWeek = (int) $date->dayOfWeek; // 0=Sunday, 6=Saturday
        if (! in_array($dayOfWeek, $this->businessDays, true)) {
            return false;
        }

        $dateString = $date->toDateString();
        if (in_array($dateString, $this->holidays, true)) {
            return false;
        }

        return true;
    }

    /**
     * Advance a timestamp to the next opening of business hours if currently outside.
     */
    public function forwardToBusinessStart(Carbon $time): Carbon
    {
        $cursor = $time->copy();

        while (true) {
            if ($this->isBusinessDay($cursor)) {
                $start = $cursor->copy()->setTime($this->startHour, $this->startMinute, 0);
                $end = $cursor->copy()->setTime($this->endHour, $this->endMinute, 0);

                if ($cursor->lt($start)) {
                    // Before morning opening: set to start of today
                    return $start;
                }

                if ($cursor->gte($start) && $cursor->lt($end)) {
                    // Inside working hours: keep current time
                    return $cursor;
                }

                // After evening close: roll forward to next day
            }

            // Move to start of next day
            $cursor->addDay()->setTime($this->startHour, $this->startMinute, 0);
        }
    }

    /**
     * Get the start of the next business day.
     */
    protected function getNextBusinessDayStart(Carbon $current): Carbon
    {
        $next = $current->copy()->addDay()->setTime($this->startHour, $this->startMinute, 0);
        while (! $this->isBusinessDay($next)) {
            $next->addDay();
        }
        return $next;
    }
}

