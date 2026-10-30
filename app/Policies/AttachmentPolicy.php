<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Determine whether the user can download/view the attachment.
     */
    public function download(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Ticket) {
            return (new TicketPolicy())->view($user, $attachable);
        }

        if ($attachable instanceof TicketReply) {
            // If it belongs to an internal note, only agents and admins can download
            if ($attachable->is_internal && ! $user->canManageTickets()) {
                return false;
            }

            return (new TicketPolicy())->view($user, $attachable->ticket);
        }

        return false;
    }
}

