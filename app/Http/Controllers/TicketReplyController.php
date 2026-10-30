<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Notifications\TicketRepliedNotification;
use App\Services\TicketActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TicketReplyController extends Controller
{
    public function __construct(
        protected TicketActivityLogger $activityLogger
    ) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $validated = $request->validate([
            'message' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,zip,txt,csv'],
        ]);

        $user = $request->user();

        // Enforce authorization for internal notes: Customers are strictly forbidden from creating internal notes
        $isInternal = false;
        if ($request->boolean('is_internal')) {
            if (! $user->canManageTickets()) {
                abort(403, 'Customers are not permitted to submit internal notes.');
            }
            $isInternal = true;
        }

        DB::transaction(function () use ($ticket, $user, $validated, $isInternal, $request) {
            $reply = TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal' => $isInternal,
            ]);

            // Save reply attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store("attachments/replies/{$reply->id}", 'local');
                    $reply->attachments()->create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by_user_id' => $user->id,
                    ]);
                }
            }

            // Automatic status progression on conversation flow
            if (! $isInternal) {
                if ($user->isCustomer()) {
                    // When customer replies, move ticket to in_progress
                    if (in_array($ticket->status, [TicketStatus::WAITING_CUSTOMER, TicketStatus::RESOLVED])) {
                        $ticket->update(['status' => TicketStatus::IN_PROGRESS]);
                    }
                } else {
                    // Agent replied publicly
                    if ($ticket->status === TicketStatus::OPEN) {
                        $ticket->update(['status' => TicketStatus::IN_PROGRESS]);
                    }
                }
            }

            // Log activity
            $eventType = $isInternal ? 'internal_note_added' : 'replied';
            $description = $isInternal
                ? "Internal note added by {$user->name}"
                : "Reply posted by {$user->name}";

            $this->activityLogger->log($ticket, $eventType, $description, $user, [
                'reply_id' => $reply->id,
                'is_internal' => $isInternal,
            ]);

            // Queue notifications
            if ($isInternal) {
                // Internal notes are only sent to assigned agent (if not the author)
                if ($ticket->assignedAgent && $ticket->assigned_to_user_id !== $user->id) {
                    $ticket->assignedAgent->notify(new TicketRepliedNotification($ticket, $reply));
                }
            } else {
                // Public reply: notify the other party
                if ($user->isCustomer()) {
                    // Customer replied -> notify assigned agent
                    if ($ticket->assignedAgent) {
                        $ticket->assignedAgent->notify(new TicketRepliedNotification($ticket, $reply));
                    }
                } else {
                    // Agent/Admin replied -> notify customer creator
                    if ($ticket->created_by_user_id !== $user->id) {
                        $ticket->creator->notify(new TicketRepliedNotification($ticket, $reply));
                    }
                }
            }
        });

        $message = $isInternal ? 'Internal note added.' : 'Reply sent successfully.';

        return back()->with('status', $message);
    }
}

