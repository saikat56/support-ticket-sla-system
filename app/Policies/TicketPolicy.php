<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the specific ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->canManageTickets()) {
            return true;
        }

        return $ticket->created_by_user_id === $user->id;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->canManageTickets()) {
            return true;
        }

        return $ticket->created_by_user_id === $user->id && ! $ticket->isClosed();
    }

    /**
     * Determine whether the user can claim an unassigned ticket.
     */
    public function claim(User $user, Ticket $ticket): bool
    {
        return $user->canManageTickets() && $ticket->assigned_to_user_id === null;
    }

    /**
     * Determine whether the user can assign tickets.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can change ticket status.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        if ($user->canManageTickets()) {
            return true;
        }

        // Customers can close their own ticket
        return $ticket->created_by_user_id === $user->id;
    }

    /**
     * Determine whether the user can change priority.
     */
    public function changePriority(User $user, Ticket $ticket): bool
    {
        return $user->canManageTickets();
    }

    /**
     * Determine whether the user can view internal notes.
     */
    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return $user->canManageTickets();
    }

    /**
     * Determine whether the user can post internal notes.
     */
    public function createInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->canManageTickets();
    }

    /**
     * Determine whether the user can reply to the ticket.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        return $this->view($user, $ticket);
    }
}

