<?php

namespace App\Console\Commands;

use App\Enums\TicketSlaStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketSlaApproachingNotification;
use App\Notifications\TicketSlaBreachedNotification;
use App\Services\SlaCalculatorService;
use App\Services\TicketActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckTicketSlaCommand extends Command
{
    protected $signature = 'tickets:check-sla';
    protected $description = 'Scan active tickets to identify SLA approaching deadlines, breaches, and automatic escalations';

    public function handle(SlaCalculatorService $slaCalculator, TicketActivityLogger $activityLogger): int
    {
        $this->info('Scanning active tickets for SLA status and escalations...');

        $activeTickets = Ticket::with(['assignedAgent', 'creator'])
            ->whereNotIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value])
            ->whereNotNull('sla_due_at')
            ->get();

        $breachedCount = 0;
        $approachingCount = 0;

        foreach ($activeTickets as $ticket) {
            $newStatus = $slaCalculator->evaluateSlaStatus($ticket);

            // Handle Breach & Escalation
            if ($newStatus === TicketSlaStatus::BREACHED) {
                if ($ticket->sla_status !== TicketSlaStatus::BREACHED || ! $ticket->is_escalated) {
                    $ticket->update([
                        'sla_status' => TicketSlaStatus::BREACHED,
                        'is_escalated' => true,
                    ]);

                    $activityLogger->log(
                        $ticket,
                        'escalated',
                        "Ticket breached resolution SLA ({$slaCalculator->getSlaHours($ticket->priority)}h) and was automatically escalated.",
                        null,
                        ['sla_status' => 'breached', 'is_escalated' => true]
                    );

                    // Notify assigned agent and all administrators
                    $recipients = collect();
                    if ($ticket->assignedAgent) {
                        $recipients->push($ticket->assignedAgent);
                    }
                    $admins = User::where('role', UserRole::ADMIN)->where('is_active', true)->get();
                    $recipients = $recipients->merge($admins)->unique('id');

                    Notification::send($recipients, new TicketSlaBreachedNotification($ticket));
                    $breachedCount++;
                }
            } elseif ($newStatus === TicketSlaStatus::APPROACHING) {
                if ($ticket->sla_status !== TicketSlaStatus::APPROACHING) {
                    $ticket->update([
                        'sla_status' => TicketSlaStatus::APPROACHING,
                    ]);

                    $activityLogger->log(
                        $ticket,
                        'approaching_breach',
                        'Ticket is approaching its resolution SLA deadline warning threshold.',
                        null,
                        ['sla_status' => 'approaching']
                    );

                    // Notify assigned agent (or admins if unassigned)
                    $recipients = $ticket->assignedAgent
                        ? collect([$ticket->assignedAgent])
                        : User::where('role', UserRole::ADMIN)->where('is_active', true)->get();

                    Notification::send($recipients, new TicketSlaApproachingNotification($ticket));
                    $approachingCount++;
                }
            }
        }

        $this->info("Scan completed. Breached & Escalated: {$breachedCount}, Approaching Warnings: {$approachingCount}.");

        return Command::SUCCESS;
    }
}

