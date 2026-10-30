<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketSlaStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Services\SlaCalculatorService;
use App\Services\TicketActivityLogger;
use App\Services\TicketAssignmentService;
use App\Services\TicketIdGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        protected SlaCalculatorService $slaCalculator,
        protected TicketIdGenerator $idGenerator,
        protected TicketActivityLogger $activityLogger,
        protected TicketAssignmentService $assignmentService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $tab = $request->get('tab', 'active'); // 'active', 'unassigned', 'my_tickets', 'resolved', 'all'

        $query = Ticket::query()
            ->with(['category', 'creator', 'assignedAgent'])
            ->withCount(['replies as replies_count' => function ($q) use ($user) {
                $q->visibleTo($user);
            }])
            ->accessibleTo($user);

        // Apply tab filters
        match ($tab) {
            'active' => $query->active(),
            'unassigned' => $query->unassigned()->active(),
            'my_tickets' => $query->where('assigned_to_user_id', $user->id)->active(),
            'resolved' => $query->resolvedOrClosed(),
            'all' => $query,
            default => $query->active(),
        };

        // Apply search & multi-field filters
        $query->filter($request->only([
            'search',
            'status',
            'priority',
            'category_id',
            'assigned_to',
            'sla_status',
            'is_escalated',
        ]));

        $tickets = $query->latest()->paginate(15)->withQueryString();

        $categories = Category::active()->orderBy('name')->get();
        $agents = User::where('role', UserRole::AGENT)->where('is_active', true)->orderBy('name')->get();

        return view('tickets.index', compact('tickets', 'categories', 'agents', 'tab'));
    }

    public function create(): View
    {
        $categories = Category::active()->orderBy('name')->get();
        $priorities = TicketPriority::cases();

        return view('tickets.create', compact('categories', 'priorities'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'description' => ['required', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,zip,txt,csv'],
        ]);

        $user = $request->user();
        $priority = TicketPriority::from($validated['priority']);
        $createdAt = now();
        $slaDueAt = $this->slaCalculator->calculateDueDate($priority, $createdAt);
        $ticketNumber = $this->idGenerator->generate();

        $ticket = DB::transaction(function () use ($validated, $user, $priority, $createdAt, $slaDueAt, $ticketNumber, $request) {
            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'priority' => $priority,
                'status' => TicketStatus::OPEN,
                'sla_status' => TicketSlaStatus::WITHIN_SLA,
                'sla_due_at' => $slaDueAt,
                'category_id' => $validated['category_id'],
                'created_by_user_id' => $user->id,
                'created_at' => $createdAt,
            ]);

            // Save attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store("attachments/tickets/{$ticket->id}", 'local');
                    $ticket->attachments()->create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by_user_id' => $user->id,
                    ]);
                }
            }

            $this->activityLogger->log(
                $ticket,
                'created',
                "Ticket created by {$user->name}",
                $user,
                [
                    'priority' => $priority->value,
                    'sla_due_at' => $slaDueAt->toDateTimeString(),
                ]
            );

            return $ticket;
        });

        // Send email notification to creator
        $user->notify(new TicketCreatedNotification($ticket));

        return redirect()->route('tickets.show', $ticket)
            ->with('status', "Ticket {$ticket->ticket_number} created successfully.");
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $user = $request->user();

        // Enforce server-side authorization check via TicketPolicy
        Gate::authorize('view', $ticket);

        $ticket->load([
            'category',
            'creator',
            'assignedAgent',
            'attachments.uploader',
            'activities.user',
        ]);

        // Filter replies: internal notes are strictly hidden for customers!
        $replies = $ticket->replies()
            ->with(['user', 'attachments.uploader'])
            ->visibleTo($user)
            ->oldest()
            ->get();

        $agents = User::where('role', UserRole::AGENT)->where('is_active', true)->orderBy('name')->get();
        $priorities = TicketPriority::cases();
        $statuses = TicketStatus::cases();

        return view('tickets.show', compact('ticket', 'replies', 'agents', 'priorities', 'statuses'));
    }

    /**
     * Concurrency-safe ticket claim by agent.
     */
    public function claim(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('claim', $ticket);

        try {
            $this->assignmentService->claimTicket($ticket, $user);
            return back()->with('status', "You have successfully claimed Ticket {$ticket->ticket_number}.");
        } catch (TicketAlreadyAssignedException $e) {
            return back()->withErrors(['assignment' => $e->getMessage()]);
        }
    }

    /**
     * Manual assignment by administrator.
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('assign', $ticket);

        $validated = $request->validate([
            'agent_id' => ['required', 'exists:users,id'],
        ]);

        $agent = User::where('id', $validated['agent_id'])->where('role', UserRole::AGENT)->firstOrFail();

        $this->assignmentService->assignManually($ticket, $agent, $request->user());

        return back()->with('status', "Ticket assigned to {$agent->name}.");
    }

    /**
     * Automatic workload-based assignment.
     */
    public function autoAssign(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('assign', $ticket);

        $assignedAgent = $this->assignmentService->assignAutomatically($ticket);

        if ($assignedAgent) {
            return back()->with('status', "Ticket automatically assigned to {$assignedAgent->name} (Workload-balanced).");
        }

        return back()->withErrors(['assignment' => 'No active support agents available for automatic assignment.']);
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('changeStatus', $ticket);

        $validated = $request->validate([
            'status' => ['required', 'in:open,in_progress,waiting_customer,resolved,closed'],
        ]);

        $newStatus = TicketStatus::from($validated['status']);
        $oldStatus = $ticket->status;

        $updates = ['status' => $newStatus];

        if ($newStatus === TicketStatus::RESOLVED && ! $ticket->resolved_at) {
            $updates['resolved_at'] = now();
        }
        if ($newStatus === TicketStatus::CLOSED && ! $ticket->closed_at) {
            $updates['closed_at'] = now();
        }

        $ticket->update($updates);

        $user = $request->user();
        $this->activityLogger->log(
            $ticket,
            'status_changed',
            "Status changed from {$oldStatus->label()} to {$newStatus->label()} by {$user->name}",
            $user,
            ['old_status' => $oldStatus->value, 'new_status' => $newStatus->value]
        );

        if ($newStatus === TicketStatus::RESOLVED) {
            $ticket->creator->notify(new TicketResolvedNotification($ticket));
        }

        return back()->with('status', "Ticket status updated to {$newStatus->label()}.");
    }

    /**
     * Update ticket priority and recalculate SLA.
     */
    public function updatePriority(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('changePriority', $ticket);

        $validated = $request->validate([
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $newPriority = TicketPriority::from($validated['priority']);
        $oldPriority = $ticket->priority;

        if ($newPriority === $oldPriority) {
            return back();
        }

        // Recalculate SLA due date from original creation time
        $newSlaDueAt = $this->slaCalculator->calculateDueDate($newPriority, $ticket->created_at);
        $newSlaStatus = $this->slaCalculator->evaluateSlaStatus($ticket->fill(['priority' => $newPriority, 'sla_due_at' => $newSlaDueAt]));

        $ticket->update([
            'priority' => $newPriority,
            'sla_due_at' => $newSlaDueAt,
            'sla_status' => $newSlaStatus,
        ]);

        $user = $request->user();
        $this->activityLogger->log(
            $ticket,
            'priority_changed',
            "Priority changed from {$oldPriority->label()} to {$newPriority->label()} by {$user->name}",
            $user,
            [
                'old_priority' => $oldPriority->value,
                'new_priority' => $newPriority->value,
                'new_sla_due_at' => $newSlaDueAt->toDateTimeString(),
            ]
        );

        return back()->with('status', "Priority changed to {$newPriority->label()}. SLA recalculated to " . $newSlaDueAt->toDayDateTimeString());
    }
}

