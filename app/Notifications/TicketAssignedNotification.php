<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public ?User $assignedBy = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assignedText = $this->assignedBy
            ? "assigned to you by {$this->assignedBy->name}"
            : "automatically assigned to you based on workload balancing";

        return (new MailMessage)
            ->subject("[{$this->ticket->ticket_number}] Ticket Assigned: {$this->ticket->subject}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A support ticket has been {$assignedText}.")
            ->line("**Priority:** {$this->ticket->priority->label()}")
            ->line("**Customer:** {$this->ticket->creator->name}")
            ->line("**SLA Due Date:** " . ($this->ticket->sla_due_at ? $this->ticket->sla_due_at->toDayDateTimeString() : 'N/A'))
            ->action('Open Ticket', url("/tickets/{$this->ticket->id}"))
            ->line('Please take action before the SLA deadline.');
    }
}

