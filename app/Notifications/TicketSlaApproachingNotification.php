<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketSlaApproachingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutesLeft = now()->diffInMinutes($this->ticket->sla_due_at, false);

        return (new MailMessage)
            ->subject("[URGENT SLA WARNING] [{$this->ticket->ticket_number}] Approaching SLA Breach")
            ->greeting("Attention {$notifiable->name},")
            ->line("Ticket **{$this->ticket->ticket_number}** is approaching its resolution SLA deadline.")
            ->line("**Priority:** {$this->ticket->priority->label()}")
            ->line("**Time Remaining:** approximately {$minutesLeft} minutes")
            ->line("**SLA Deadline:** " . ($this->ticket->sla_due_at ? $this->ticket->sla_due_at->toDayDateTimeString() : 'N/A'))
            ->action('Take Action Now', url("/tickets/{$this->ticket->id}"))
            ->line('Immediate resolution or agent follow-up is required to prevent an SLA breach.');
    }
}

