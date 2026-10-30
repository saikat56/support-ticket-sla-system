<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketResolvedNotification extends Notification implements ShouldQueue
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
            ->subject("[{$this->ticket->ticket_number}] Ticket Resolved: {$this->ticket->subject}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your support ticket has been marked as **Resolved**.")
            ->line("**Resolution Time:** " . ($this->ticket->resolved_at ? $this->ticket->resolved_at->toDayDateTimeString() : 'N/A'))
            ->action('View Ticket Details', url("/tickets/{$this->ticket->id}"))
            ->line('If your issue is not completely addressed, you may reply to this ticket to reopen it.');
    }
}

