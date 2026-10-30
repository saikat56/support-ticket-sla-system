@extends('layouts.app', ['title' => 'Tickets - Support Ticket System'])

@section('content')
<div class="space-y-6">

    <!-- Header & Action Row -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Support Tickets</h1>
            <p class="text-sm text-slate-500 mt-1">Manage, filter, and track tickets under active SLA thresholds</p>
        </div>
        <div class="flex items-center space-x-3">
            @if(auth()->user()->canManageTickets())
                <a href="{{ route('imports.create') }}" class="px-3.5 py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition shadow-xs flex items-center">
                    <svg class="w-4 h-4 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    CSV Import
                </a>
            @endif
            <a href="{{ route('tickets.create') }}" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-xs transition flex items-center">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Ticket
            </a>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('tickets.index', array_merge(request()->query(), ['tab' => 'active'])) }}"
               class="whitespace-nowrap pb-4 px-1 border-b-2 font-semibold text-sm transition {{ $tab === 'active' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                Active Tickets
            </a>
            @if(auth()->user()->canManageTickets())
                <a href="{{ route('tickets.index', array_merge(request()->query(), ['tab' => 'unassigned'])) }}"
                   class="whitespace-nowrap pb-4 px-1 border-b-2 font-semibold text-sm transition {{ $tab === 'unassigned' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    Unassigned Queue
                </a>
                <a href="{{ route('tickets.index', array_merge(request()->query(), ['tab' => 'my_tickets'])) }}"
                   class="whitespace-nowrap pb-4 px-1 border-b-2 font-semibold text-sm transition {{ $tab === 'my_tickets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    My Assigned Tickets
                </a>
            @endif
            <a href="{{ route('tickets.index', array_merge(request()->query(), ['tab' => 'resolved'])) }}"
               class="whitespace-nowrap pb-4 px-1 border-b-2 font-semibold text-sm transition {{ $tab === 'resolved' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                Resolved & Closed
            </a>
            <a href="{{ route('tickets.index', array_merge(request()->query(), ['tab' => 'all'])) }}"
               class="whitespace-nowrap pb-4 px-1 border-b-2 font-semibold text-sm transition {{ $tab === 'all' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                All Tickets
            </a>
        </nav>
    </div>

    <!-- Search & Filter Card -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
        <form method="GET" action="{{ route('tickets.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <!-- Search Keyword -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-semibold text-slate-600 uppercase mb-1">Search Keyword / ID</label>
                <div class="relative">
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="TKT-2026-..., keyword..."
                           class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Priority -->
            <div>
                <label for="priority" class="block text-xs font-semibold text-slate-600 uppercase mb-1">Priority</label>
                <select name="priority" id="priority" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">All Priorities</option>
                    @foreach (\App\Enums\TicketPriority::cases() as $p)
                        <option value="{{ $p->value }}" {{ request('priority') === $p->value ? 'selected' : '' }}>
                            {{ $p->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Category -->
            <div>
                <label for="category_id" class="block text-xs font-semibold text-slate-600 uppercase mb-1">Category</label>
                <select name="category_id" id="category_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">All Categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- SLA Status -->
            <div>
                <label for="sla_status" class="block text-xs font-semibold text-slate-600 uppercase mb-1">SLA Status</label>
                <select name="sla_status" id="sla_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">All SLA Statuses</option>
                    @foreach (\App\Enums\TicketSlaStatus::cases() as $sla)
                        <option value="{{ $sla->value }}" {{ request('sla_status') === $sla->value ? 'selected' : '' }}>
                            {{ $sla->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 py-2 px-3 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-lg shadow-xs transition">
                    Filter
                </button>
                <a href="{{ route('tickets.index', ['tab' => $tab]) }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition" title="Clear filters">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Tickets Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
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
                        <th class="py-3.5 px-4 font-semibold">SLA Deadline</th>
                        <th class="py-3.5 px-4 font-semibold text-right">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tickets as $ticket)
                        <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="window.location='{{ route('tickets.show', $ticket) }}'">
                            <td class="py-4 px-6 font-mono font-bold text-indigo-600 whitespace-nowrap">
                                {{ $ticket->ticket_number }}
                                @if($ticket->is_escalated)
                                    <span class="inline-block ml-1 px-1.5 py-0.2 text-[10px] font-bold bg-rose-600 text-white rounded">ESCALATED</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 max-w-xs">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-600 block line-clamp-1">
                                    {{ $ticket->subject }}
                                </a>
                                <div class="flex items-center space-x-2 text-xs text-slate-500 mt-0.5">
                                    <span>{{ $ticket->category->name }}</span>
                                    <span>&bull;</span>
                                    <span class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-0.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        {{ $ticket->replies_count }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-4 px-4 text-slate-700 whitespace-nowrap">
                                {{ $ticket->creator->name }}
                            </td>
                            <td class="py-4 px-4 whitespace-nowrap">
                                @if($ticket->assignedAgent)
                                    <div class="flex items-center space-x-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span class="text-slate-800 font-medium">{{ $ticket->assignedAgent->name }}</span>
                                    </div>
                                @else
                                    <span class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                        Unassigned
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span class="text-xs px-2.5 py-1 rounded-full border {{ $ticket->priority->badgeClasses() }}">
                                    {{ $ticket->priority->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span class="text-xs px-2.5 py-1 rounded-full border {{ $ticket->status->badgeClasses() }}">
                                    {{ $ticket->status->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-4 whitespace-nowrap">
                                <div>
                                    <span class="text-xs px-2.5 py-1 rounded-full border {{ $ticket->sla_status->badgeClasses() }}">
                                        {{ $ticket->sla_status->label() }}
                                    </span>
                                </div>
                                @if($ticket->sla_due_at)
                                    <div class="text-[11px] text-slate-500 mt-1">
                                        Due: {{ $ticket->sla_due_at->format('M d, H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right whitespace-nowrap text-xs text-slate-500">
                                {{ $ticket->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                No tickets match your current filters or selected view.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($tickets->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

