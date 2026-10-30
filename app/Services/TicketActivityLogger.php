<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;

class TicketActivityLogger
{
    /**
     * Log a ticket activity event.
     */
    public function log(Ticket $ticket, string $eventType, string $description, ?User $user = null, ?array $properties = null): TicketActivity
    {
        return TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user?->id ?? auth()->id(),
            'event_type' => $eventType,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}

