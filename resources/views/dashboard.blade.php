@extends('layouts.app', ['title' => 'Dashboard - Support Ticket System'])

@section('content')
<div class="space-y-6">

    <!-- Welcome Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Support Overview</h1>
            <p class="text-sm text-slate-500 mt-1">
                Welcome back, <strong class="text-slate-800">{{ auth()->user()->name }}</strong>.
                You are operating as <span class="font-semibold text-indigo-600">{{ auth()->user()->role->label() }}</span>.
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('tickets.index', ['tab' => 'unassigned']) }}" class="px-3.5 py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition shadow-xs">
                View Unassigned Queue
            </a>
            <a href="{{ route('tickets.create') }}" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-xs transition">
                + Create Ticket
            </a>
        </div>
    </div>

    <!-- Top Key Metrics Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- Open Tickets -->
        <a href="{{ route('tickets.index', ['tab' => 'active']) }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-indigo-300 transition">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Open Tickets</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-3xl font-extrabold text-slate-900">{{ $openTickets }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">Active</span>
            </div>
        </a>

        <!-- Unassigned Tickets -->
        <a href="{{ route('tickets.index', ['tab' => 'unassigned']) }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-amber-300 transition">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Unassigned</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-3xl font-extrabold text-amber-600">{{ $unassignedTickets }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">Need Agent</span>
            </div>
        </a>

        <!-- Critical Tickets -->
        <a href="{{ route('tickets.index', ['priority' => 'critical', 'tab' => 'active']) }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-red-300 transition">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Critical Priority</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-3xl font-extrabold text-red-600">{{ $criticalTickets }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-700">2h SLA</span>
            </div>
        </a>

        <!-- Approaching SLA -->
        <a href="{{ route('tickets.index', ['sla_status' => 'approaching', 'tab' => 'active']) }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-yellow-300 transition">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Approaching SLA</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-3xl font-extrabold text-yellow-600">{{ $slaApproachingTickets }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-yellow-50 text-yellow-700">Urgent</span>
            </div>
        </a>

        <!-- Breached SLA -->
        <a href="{{ route('tickets.index', ['sla_status' => 'breached']) }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-rose-300 transition">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">SLA Breached</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-3xl font-extrabold text-rose-600">{{ $slaBreachedTickets }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700">Escalated</span>
            </div>
        </a>

        <!-- Resolved Tickets -->
        <a href="{{ route('tickets.index', ['tab' => 'resolved']) }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs hover:border-teal-300 transition">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Resolved</span>
            <div class="flex items-baseline justify-between mt-2">
                <span class="text-3xl font-extrabold text-teal-600">{{ $resolvedTickets }}</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-teal-50 text-teal-700">Done</span>
            </div>
        </a>
    </div>

    <!-- Secondary Row: Priority Distribution & Agent Workloads -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Priority Breakdown -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
            <h2 class="text-base font-bold text-slate-900 mb-4">Tickets by Priority</h2>
            <div class="space-y-3">
                @php
                    $prioritiesMeta = [
                        'critical' => ['label' => 'Critical (2 Hours)', 'color' => 'bg-red-500', 'text' => 'text-red-700'],
                        'high' => ['label' => 'High (8 Hours)', 'color' => 'bg-amber-500', 'text' => 'text-amber-700'],
                        'medium' => ['label' => 'Medium (24 Hours)', 'color' => 'bg-blue-500', 'text' => 'text-blue-700'],
                        'low' => ['label' => 'Low (48 Hours)', 'color' => 'bg-slate-400', 'text' => 'text-slate-700'],
                    ];
                @endphp
                @foreach ($prioritiesMeta as $key => $meta)
                    @php $count = $priorityCounts[$key] ?? 0; @endphp
                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span>{{ $meta['label'] }}</span>
                            <span class="{{ $meta['text'] }}">{{ $count }}</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="{{ $meta['color'] }} h-2 rounded-full" style="width: {{ $totalTickets > 0 ? ($count / $totalTickets) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Business Hours Notice -->
            <div class="mt-6 p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600">
                <p class="font-semibold text-slate-800 flex items-center">
                    <svg class="w-4 h-4 mr-1 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    SLA Business Calendar
                </p>
                <p class="mt-1">Active business hours: <span class="font-medium">Sunday to Thursday (09:00 - 18:00 UTC)</span>. Non-working hours & weekends are automatically skipped.</p>
            </div>
        </div>

        <!-- Agent Workload Leaderboard (Admins & Agents) -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
            <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center justify-between">
                <span>Support Team Workload</span>
                <span class="text-xs font-normal text-slate-500">Auto-balanced by active ticket count</span>
            </h2>

            @if($agentWorkloads->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase tracking-wider">
                                <th class="pb-3 font-semibold">Agent</th>
                                <th class="pb-3 font-semibold text-center">Active Workload</th>
                                <th class="pb-3 font-semibold text-center">Resolved</th>
                                <th class="pb-3 font-semibold text-center">Total Assigned</th>
                                <th class="pb-3 font-semibold text-right">Last Assigned</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($agentWorkloads as $agent)
                                <tr>
                                    <td class="py-3 font-medium text-slate-900 flex items-center space-x-2">
                                        <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs">
                                            {{ substr($agent->name, 0, 1) }}
                                        </div>
                                        <span>{{ $agent->name }}</span>
                                    </td>
                                    <td class="py-3 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $agent->active_assigned > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $agent->active_assigned }} tickets
                                        </span>
                                    </td>
                                    <td class="py-3 text-center text-slate-600">{{ $agent->resolved_assigned }}</td>
                                    <td class="py-3 text-center text-slate-600 font-semibold">{{ $agent->total_assigned }}</td>
                                    <td class="py-3 text-right text-xs text-slate-500">
                                        {{ $agent->last_assigned_at ? $agent->last_assigned_at->diffForHumans() : 'Never' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-slate-500 text-sm">
                    Workload balancing is visible to Support Agents and Administrators.
                </div>
            @endif
        </div>

    </div>

    <!-- Recent Active Tickets Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-base font-bold text-slate-900">Recent Active Tickets</h2>
            <a href="{{ route('tickets.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                View all tickets &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="py-3.5 px-6 font-semibold">Ticket ID</th>
                        <th class="py-3.5 px-4 font-semibold">Subject & Category</th>
                        <th class="py-3.5 px-4 font-semibold">Customer</th>
                        <th class="py-3.5 px-4 font-semibold">Assigned Agent</th>
                        <th class="py-3.5 px-4 font-semibold">Priority</th>
                        <th class="py-3.5 px-4 font-semibold">Status</th>
                        <th class="py-3.5 px-4 font-semibold">SLA Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentTickets as $ticket)
                        <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="window.location='{{ route('tickets.show', $ticket) }}'">
                            <td class="py-4 px-6 font-mono font-bold text-indigo-600">
                                {{ $ticket->ticket_number }}
                            </td>
                            <td class="py-4 px-4">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-600 block line-clamp-1">
                                    {{ $ticket->subject }}
                                </a>
                                <span class="text-xs text-slate-500">{{ $ticket->category->name }}</span>
                            </td>
                            <td class="py-4 px-4 text-slate-700">
                                {{ $ticket->creator->name }}
                            </td>
                            <td class="py-4 px-4">
                                @if($ticket->assignedAgent)
                                    <span class="text-slate-800 font-medium">{{ $ticket->assignedAgent->name }}</span>
                                @else
                                    <span class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-4 px-4">
                                <span class="text-xs px-2.5 py-1 rounded-full border {{ $ticket->priority->badgeClasses() }}">
                                    {{ $ticket->priority->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="text-xs px-2.5 py-1 rounded-full border {{ $ticket->status->badgeClasses() }}">
                                    {{ $ticket->status->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="text-xs px-2.5 py-1 rounded-full border {{ $ticket->sla_status->badgeClasses() }}">
                                    {{ $ticket->sla_status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                No active tickets found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

