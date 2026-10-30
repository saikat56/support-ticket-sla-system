<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketSlaBreachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("[CRITICAL SLA BREACH] [{$this->ticket->ticket_number}] Has Breached SLA & Escalated")
            ->greeting("Attention {$notifiable->name},")
            ->line("Ticket **{$this->ticket->ticket_number}** has **EXCEEDED** its SLA resolution deadline and has been marked as **ESCALATED**.")
            ->line("**Priority:** {$this->ticket->priority->label()}")
            ->line("**Assigned Agent:** " . ($this->ticket->assignedAgent?->name ?? 'Unassigned'))
            ->line("**Original Due Date:** " . ($this->ticket->sla_due_at ? $this->ticket->sla_due_at->toDayDateTimeString() : 'N/A'))
            ->action('Review Escalated Ticket', url("/tickets/{$this->ticket->id}"))
            ->line('Supervisor review and immediate expedited resolution is required.');
    }
}

