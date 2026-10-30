<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public TicketReply $reply
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $authorName = $this->reply->user->name;
        $isInternal = $this->reply->is_internal;

        $subjectPrefix = $isInternal ? '[INTERNAL NOTE]' : '[NEW REPLY]';

        $mail = (new MailMessage)
            ->subject("{$subjectPrefix} [{$this->ticket->ticket_number}] {$this->ticket->subject}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$authorName} has added a " . ($isInternal ? 'internal note' : 'reply') . " to ticket {$this->ticket->ticket_number}:")
            ->line('"' . \Illuminate\Support\Str::limit($this->reply->message, 300) . '"')
            ->action('View Conversation', url("/tickets/{$this->ticket->id}"));

        return $mail;
    }
}

