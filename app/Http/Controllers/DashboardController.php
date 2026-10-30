<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketSlaStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Ticket::query()->accessibleTo($user);

        // High-level Metric Counts
        $totalTickets = (clone $query)->count();

        $openTickets = (clone $query)
            ->whereIn('status', [TicketStatus::OPEN->value, TicketStatus::IN_PROGRESS->value, TicketStatus::WAITING_CUSTOMER->value])
            ->count();

        $unassignedTickets = (clone $query)
            ->whereNull('assigned_to_user_id')
            ->whereNotIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value])
            ->count();

        $criticalTickets = (clone $query)
            ->where('priority', TicketPriority::CRITICAL->value)
            ->whereNotIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value])
            ->count();

        $slaBreachedTickets = (clone $query)
            ->where('sla_status', TicketSlaStatus::BREACHED->value)
            ->whereNotIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value])
            ->count();

        $slaApproachingTickets = (clone $query)
            ->where('sla_status', TicketSlaStatus::APPROACHING->value)
            ->whereNotIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value])
            ->count();

        $resolvedTickets = (clone $query)
            ->whereIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value])
            ->count();

        // Tickets grouped by Priority
        $priorityCounts = (clone $query)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Agent Workloads (Only for staff: Admins and Agents)
        $agentWorkloads = collect();
        if ($user->canManageTickets()) {
            $agentWorkloads = User::where('role', UserRole::AGENT)
                ->where('is_active', true)
                ->withCount([
                    'assignedTickets as total_assigned',
                    'assignedTickets as active_assigned' => function ($q) {
                        $q->whereIn('status', [TicketStatus::OPEN->value, TicketStatus::IN_PROGRESS->value, TicketStatus::WAITING_CUSTOMER->value]);
                    },
                    'assignedTickets as resolved_assigned' => function ($q) {
                        $q->whereIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value]);
                    },
                ])
                ->orderBy('active_assigned', 'desc')
                ->get();
        }

        // Recent Active Tickets
        $recentTickets = (clone $query)
            ->with(['category', 'creator', 'assignedAgent'])
            ->active()
            ->latest()
            ->take(6)
            ->get();

        return view('dashboard', compact(
            'totalTickets',
            'openTickets',
            'unassignedTickets',
            'criticalTickets',
            'slaBreachedTickets',
            'slaApproachingTickets',
            'resolvedTickets',
            'priorityCounts',
            'agentWorkloads',
            'recentTickets'
        ));
    }
}

