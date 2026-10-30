<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
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
            ->subject("[{$this->ticket->ticket_number}] Ticket Created: {$this->ticket->subject}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your support ticket has been received and logged.")
            ->line("**Ticket Number:** {$this->ticket->ticket_number}")
            ->line("**Priority:** {$this->ticket->priority->label()}")
            ->line("**SLA Due Date:** " . ($this->ticket->sla_due_at ? $this->ticket->sla_due_at->toDayDateTimeString() : 'N/A'))
            ->action('View Ticket Details', url("/tickets/{$this->ticket->id}"))
            ->line('Our support team will review your ticket promptly.');
    }
}

