<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use Illuminate\Support\Facades\DB;

class TicketAssignmentService
{
    public function __construct(
        protected TicketActivityLogger $activityLogger
    ) {}

    /**
     * Concurrency-safe claim mechanism.
     * Uses pessimistic row-level locking (SELECT FOR UPDATE) to eliminate race conditions
     * when multiple agents attempt to claim the same unassigned ticket simultaneously.
     *
     * @throws TicketAlreadyAssignedException
     */
    public function claimTicket(Ticket $ticket, User $agent): Ticket
    {
        return DB::transaction(function () use ($ticket, $agent) {
            /** @var Ticket $lockedTicket */
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTicket->assigned_to_user_id !== null) {
                $assigneeName = $lockedTicket->assignedAgent?->name ?? 'another agent';
                throw new TicketAlreadyAssignedException("Ticket has already been assigned to {$assigneeName}.");
            }

            $lockedTicket->update([
                'assigned_to_user_id' => $agent->id,
                'status' => TicketStatus::IN_PROGRESS,
            ]);

            $agent->update(['last_assigned_at' => now()]);

            $this->activityLogger->log(
                $lockedTicket,
                'claimed',
                "Ticket claimed by {$agent->name}",
                $agent,
                ['assigned_to' => $agent->id]
            );

            return $lockedTicket;
        });
    }

    /**
     * Manually assign an agent to a ticket (typically by Administrator).
     */
    public function assignManually(Ticket $ticket, User $agent, User $assignedBy): Ticket
    {
        return DB::transaction(function () use ($ticket, $agent, $assignedBy) {
            /** @var Ticket $lockedTicket */
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousAgent = $lockedTicket->assignedAgent?->name;

            $lockedTicket->update([
                'assigned_to_user_id' => $agent->id,
                'status' => $lockedTicket->status === TicketStatus::OPEN ? TicketStatus::IN_PROGRESS : $lockedTicket->status,
            ]);

            $agent->update(['last_assigned_at' => now()]);

            $description = $previousAgent
                ? "Reassigned from {$previousAgent} to {$agent->name} by {$assignedBy->name}"
                : "Assigned to {$agent->name} by {$assignedBy->name}";

            $this->activityLogger->log(
                $lockedTicket,
                'assigned',
                $description,
                $assignedBy,
                [
                    'previous_agent' => $previousAgent,
                    'new_agent' => $agent->name,
                ]
            );

            // Queue notification to the newly assigned agent
            $agent->notify(new TicketAssignedNotification($lockedTicket, $assignedBy));

            return $lockedTicket;
        });
    }

    /**
     * Automatic ticket assignment strategy:
     * 1. Filters active agents (role='agent', is_active=true)
     * 2. Calculates current active ticket workload per agent
     * 3. Selects agent with the lowest active workload
     * 4. Ties broken by longest time since last assignment (round-robin fallback)
     */
    public function assignAutomatically(Ticket $ticket): ?User
    {
        return DB::transaction(function () use ($ticket) {
            /** @var Ticket $lockedTicket */
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTicket->assigned_to_user_id !== null) {
                return $lockedTicket->assignedAgent;
            }

            // Find best candidate agent
            $selectedAgent = User::where('role', UserRole::AGENT)
                ->where('is_active', true)
                ->withCount([
                    'assignedTickets as active_workload' => function ($query) {
                        $query->whereIn('status', [
                            TicketStatus::OPEN->value,
                            TicketStatus::IN_PROGRESS->value,
                            TicketStatus::WAITING_CUSTOMER->value,
                        ]);
                    },
                ])
                ->orderBy('active_workload', 'asc')
                ->orderByRaw('last_assigned_at IS NULL DESC')
                ->orderBy('last_assigned_at', 'asc')
                ->first();

            if (! $selectedAgent) {
                return null;
            }

            $lockedTicket->update([
                'assigned_to_user_id' => $selectedAgent->id,
                'status' => TicketStatus::IN_PROGRESS,
            ]);

            $selectedAgent->update(['last_assigned_at' => now()]);

            $this->activityLogger->log(
                $lockedTicket,
                'assigned',
                "Automatically assigned to {$selectedAgent->name} (Workload: {$selectedAgent->active_workload} active tickets)",
                null,
                [
                    'agent_id' => $selectedAgent->id,
                    'strategy' => 'least_workload_round_robin',
                    'workload' => $selectedAgent->active_workload,
                ]
            );

            // Queue notification
            $selectedAgent->notify(new TicketAssignedNotification($lockedTicket, null));

            return $selectedAgent;
        });
    }
}

