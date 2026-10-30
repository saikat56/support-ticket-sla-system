@extends('layouts.app', ['title' => "Ticket {$ticket->ticket_number} - Support Ticket System"])

@section('content')
<div class="space-y-6">

    <!-- Top Breadcrumb & Quick Actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center space-x-3">
            <a href="{{ route('tickets.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800 flex items-center">
                &larr; All Tickets
            </a>
            <span class="text-slate-300">/</span>
            <span class="font-mono font-bold text-indigo-600 text-sm">{{ $ticket->ticket_number }}</span>
        </div>

        <!-- Claim Ticket / Assignment Actions -->
        <div class="flex items-center flex-wrap gap-2">
            @can('claim', $ticket)
                <form action="{{ route('tickets.claim', $ticket) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-xs transition flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Claim This Ticket
                    </button>
                </form>
            @endcan

            @can('assign', $ticket)
                <!-- Auto-Assign Button -->
                <form action="{{ route('tickets.auto-assign', $ticket) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg shadow-xs transition" title="Assign based on active agent workload">
                        Auto-Assign (Workload)
                    </button>
                </form>

                <!-- Manual Assign Modal / Dropdown -->
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="px-3.5 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-lg shadow-xs transition">
                        Assign Agent &darr;
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-56 rounded-xl shadow-lg bg-white ring-1 ring-black/5 p-3 z-50">
                        <form action="{{ route('tickets.assign', $ticket) }}" method="POST">
                            @csrf
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Select Support Agent</label>
                            <select name="agent_id" class="w-full text-xs border border-slate-300 rounded p-1.5 mb-2 focus:ring-indigo-500">
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ $ticket->assigned_to_user_id === $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full py-1.5 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700">
                                Confirm Assignment
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    <!-- Ticket Header Banner -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="space-y-2">
                <div class="flex items-center flex-wrap gap-2">
                    <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ $ticket->ticket_number }}
                    </span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full border {{ $ticket->priority->badgeClasses() }}">
                        {{ $ticket->priority->label() }} Priority
                    </span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full border {{ $ticket->status->badgeClasses() }}">
                        {{ $ticket->status->label() }}
                    </span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full border {{ $ticket->sla_status->badgeClasses() }}">
                        {{ $ticket->sla_status->label() }}
                    </span>
                    @if($ticket->is_escalated)
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-rose-600 text-white">
                            ESCALATED
                        </span>
                    @endif
                </div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $ticket->subject }}</h1>
                <p class="text-xs text-slate-500">
                    Created by <strong class="text-slate-700">{{ $ticket->creator->name }}</strong> on {{ $ticket->created_at->format('M d, Y \a\t H:i') }} ({{ $ticket->created_at->diffForHumans() }})
                </p>
            </div>

            <!-- Controls: Status & Priority Switchers -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <!-- Status Switcher -->
                @can('changeStatus', $ticket)
                    <form action="{{ route('tickets.update-status', $ticket) }}" method="POST" class="flex items-center">
                        @csrf
                        @method('PATCH')
                        <select name="status" onchange="this.form.submit()"
                                class="text-xs font-semibold rounded-lg border border-slate-300 py-1.5 pl-2.5 pr-7 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                            @foreach ($statuses as $st)
                                @if(auth()->user()->canManageTickets() || in_array($st->value, ['resolved', 'closed', $ticket->status->value]))
                                    <option value="{{ $st->value }}" {{ $ticket->status === $st ? 'selected' : '' }}>
                                        Status: {{ $st->label() }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </form>
                @endcan

                <!-- Priority Switcher (Staff) -->
                @can('changePriority', $ticket)
                    <form action="{{ route('tickets.update-priority', $ticket) }}" method="POST" class="flex items-center">
                        @csrf
                        @method('PATCH')
                        <select name="priority" onchange="this.form.submit()"
                                class="text-xs font-semibold rounded-lg border border-slate-300 py-1.5 pl-2.5 pr-7 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                            @foreach ($priorities as $pr)
                                <option value="{{ $pr->value }}" {{ $ticket->priority === $pr ? 'selected' : '' }}>
                                    Priority: {{ $pr->label() }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endcan
            </div>
        </div>

        <!-- SLA Progress & Business Hours Details -->
        <div class="mt-6 pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="text-slate-500 block">SLA Resolution Target</span>
                <span class="font-bold text-slate-800 text-sm">
                    {{ config("sla.priorities.{$ticket->priority->value}", 24) }} Business Hours
                </span>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="text-slate-500 block">Resolution Due Date</span>
                <span class="font-bold {{ $ticket->sla_status === \App\Enums\TicketSlaStatus::BREACHED ? 'text-rose-600' : 'text-slate-800' }} text-sm">
                    {{ $ticket->sla_due_at ? $ticket->sla_due_at->format('M d, Y - H:i') : 'N/A' }}
                </span>
            </div>
            <div class="p-3 rounded-xl {{ $ticket->sla_status === \App\Enums\TicketSlaStatus::BREACHED ? 'bg-rose-50 border-rose-200 text-rose-800' : ($ticket->sla_status === \App\Enums\TicketSlaStatus::APPROACHING ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800') }} border">
                <span class="block font-medium">SLA Clock Status</span>
                <span class="font-bold text-sm">
                    @if($ticket->isClosed())
                        Resolved {{ $ticket->resolved_at ? $ticket->resolved_at->diffForHumans($ticket->created_at, true) . ' after creation' : '' }}
                    @elseif($ticket->sla_due_at)
                        @if(now()->gt($ticket->sla_due_at))
                            Breached by {{ now()->diffForHumans($ticket->sla_due_at, true) }}
                        @else
                            {{ now()->diffForHumans($ticket->sla_due_at, true) }} remaining
                        @endif
                    @endif
                </span>
            </div>
        </div>
    </div>

    <!-- Main 2-Column Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Columns: Description & Conversation Stream -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Initial Description Card -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center">
                            {{ substr($ticket->creator->name, 0, 1) }}
                        </div>
                        <div>
                            <span class="font-semibold text-slate-900 text-sm">{{ $ticket->creator->name }}</span>
                            <span class="text-xs text-slate-400 block">Submitted the ticket</span>
                        </div>
                    </div>
                    <span class="text-xs text-slate-400">{{ $ticket->created_at->format('M d, Y H:i') }}</span>
                </div>

                <div class="prose prose-sm max-w-none text-slate-800 whitespace-pre-wrap leading-relaxed">
                    {{ $ticket->description }}
                </div>

                <!-- Ticket Attachments -->
                @if($ticket->attachments->isNotEmpty())
                    <div class="pt-3 border-t border-slate-100">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-2">Attachments ({{ $ticket->attachments->count() }})</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($ticket->attachments as $att)
                                <a href="{{ route('attachments.download', $att) }}"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-200 text-xs text-slate-700 transition">
                                    <svg class="w-4 h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <span class="font-medium mr-1">{{ $att->file_name }}</span>
                                    <span class="text-slate-400">({{ $att->formatted_size }})</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Conversation Thread -->
            <div class="space-y-4">
                <h2 class="text-base font-bold text-slate-900 px-1">
                    Conversation Thread ({{ $replies->count() }})
                </h2>

                @forelse ($replies as $reply)
                    <div class="rounded-2xl border p-5 shadow-xs transition
                        {{ $reply->is_internal ? 'bg-amber-50/70 border-amber-300 ring-1 ring-amber-200' : 'bg-white border-slate-200' }}">

                        <div class="flex items-center justify-between pb-3 border-b {{ $reply->is_internal ? 'border-amber-200/80' : 'border-slate-100' }}">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs
                                    {{ $reply->is_internal ? 'bg-amber-200 text-amber-900' : ($reply->user->canManageTickets() ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-700') }}">
                                    {{ substr($reply->user->name, 0, 1) }}
                                </div>
                                <div>
                                    <span class="font-semibold text-sm text-slate-900">{{ $reply->user->name }}</span>
                                    <span class="text-[11px] px-2 py-0.2 rounded-full font-semibold ml-1.5
                                        {{ $reply->user->isAdmin() ? 'bg-purple-100 text-purple-700' : ($reply->user->isAgent() ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $reply->user->role->label() }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                @if($reply->is_internal)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-200 text-amber-900">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        INTERNAL NOTE (Hidden from Customer)
                                    </span>
                                @endif
                                <span class="text-xs text-slate-400">{{ $reply->created_at->format('M d, H:i') }} ({{ $reply->created_at->diffForHumans() }})</span>
                            </div>
                        </div>

                        <div class="mt-3 text-sm text-slate-800 whitespace-pre-wrap leading-relaxed">
                            {{ $reply->message }}
                        </div>

                        <!-- Reply Attachments -->
                        @if($reply->attachments->isNotEmpty())
                            <div class="mt-3 pt-3 border-t {{ $reply->is_internal ? 'border-amber-200/80' : 'border-slate-100' }} flex flex-wrap gap-2">
                                @foreach ($reply->attachments as $att)
                                    <a href="{{ route('attachments.download', $att) }}"
                                       class="inline-flex items-center px-2.5 py-1 rounded border border-slate-200 bg-white hover:bg-slate-50 text-xs text-slate-700 transition">
                                        <svg class="w-3.5 h-3.5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        {{ $att->file_name }} ({{ $att->formatted_size }})
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 text-center text-slate-500 text-sm">
                        No replies posted yet.
                    </div>
                @endforelse
            </div>

            <!-- Reply Box -->
            @can('reply', $ticket)
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs" x-data="{ isInternal: false }">
                    <!-- Tab Selector for Staff (Public Reply vs Internal Note) -->
                    @if(auth()->user()->canManageTickets())
                        <div class="flex items-center space-x-2 border-b border-slate-200 pb-3 mb-4">
                            <button type="button" @click="isInternal = false"
                                    :class="!isInternal ? 'bg-indigo-600 text-white font-semibold' : 'bg-slate-100 text-slate-600 font-medium hover:bg-slate-200'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs transition">
                                Public Customer Reply
                            </button>
                            <button type="button" @click="isInternal = true"
                                    :class="isInternal ? 'bg-amber-600 text-white font-semibold ring-2 ring-amber-400' : 'bg-slate-100 text-slate-600 font-medium hover:bg-slate-200'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs transition flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                Internal Agent Note
                            </button>
                        </div>
                    @endif

                    <form action="{{ route('tickets.replies.store', $ticket) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <input type="hidden" name="is_internal" :value="isInternal ? 1 : 0">

                        <div x-show="isInternal" x-cloak class="p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                            <strong>Note:</strong> Internal notes are <strong>strictly hidden from the customer</strong> and only visible to support agents and administrators.
                        </div>

                        <div>
                            <label for="message" class="block text-xs font-semibold text-slate-700 uppercase mb-1">
                                <span x-show="!isInternal">Reply to customer</span>
                                <span x-show="isInternal" class="text-amber-700">Internal Agent Note</span>
                            </label>
                            <textarea name="message" id="message" rows="4" required
                                      placeholder="Write your response or private observation..."
                                      :class="isInternal ? 'border-amber-300 focus:ring-amber-500 focus:border-amber-500 bg-amber-50/20' : 'border-slate-300 focus:ring-indigo-500 focus:border-indigo-500'"
                                      class="w-full px-3 py-2 border rounded-lg text-sm shadow-xs focus:ring-2 focus:outline-none"></textarea>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pt-2">
                            <div>
                                <label class="block text-xs text-slate-500 font-medium mb-1">Attach Files</label>
                                <input type="file" name="attachments[]" multiple class="text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-slate-100 hover:file:bg-slate-200">
                            </div>

                            <button type="submit"
                                    :class="isInternal ? 'bg-amber-600 hover:bg-amber-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                                    class="px-5 py-2 text-white text-xs font-bold rounded-lg shadow-xs transition">
                                <span x-show="!isInternal">Send Reply</span>
                                <span x-show="isInternal">Save Internal Note</span>
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="bg-slate-100 p-4 rounded-xl text-center text-xs text-slate-500">
                    This ticket is resolved or closed. Change status to In Progress to submit further replies.
                </div>
            @endcan

        </div>

        <!-- Right 1 Column: Ticket Details & Activity Audit Timeline -->
        <div class="space-y-6">

            <!-- Ticket Details Card -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Ticket Info</h2>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 block font-medium">Customer</span>
                        <span class="font-semibold text-slate-800 text-sm">{{ $ticket->creator->name }}</span>
                        <span class="text-slate-500 block">{{ $ticket->creator->email }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block font-medium">Assigned Support Agent</span>
                        @if($ticket->assignedAgent)
                            <span class="font-semibold text-slate-800 text-sm">{{ $ticket->assignedAgent->name }}</span>
                            <span class="text-slate-500 block">{{ $ticket->assignedAgent->email }}</span>
                        @else
                            <span class="font-semibold text-amber-600">Unassigned</span>
                        @endif
                    </div>

                    <div>
                        <span class="text-slate-400 block font-medium">Category</span>
                        <span class="font-semibold text-slate-800">{{ $ticket->category->name }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block font-medium">Resolution Time</span>
                        @if($ticket->resolved_at)
                            <span class="font-semibold text-teal-700">{{ $ticket->resolved_at->format('M d, Y H:i') }}</span>
                        @else
                            <span class="text-slate-500">Not yet resolved</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Activity Audit History (Traceable Chronological Log) -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center justify-between">
                    <span>Audit / Activity History</span>
                    <span class="text-[11px] font-normal text-slate-400">{{ $ticket->activities->count() }} events</span>
                </h2>

                <div class="flow-root">
                    <ul class="-mb-6 space-y-4">
                        @forelse ($ticket->activities as $activity)
                            <li class="relative pb-4">
                                <div class="flex items-start space-x-3">
                                    <div class="w-6 h-6 rounded-full bg-slate-100 border border-slate-300 flex items-center justify-center shrink-0 mt-0.5">
                                        <span class="w-2 h-2 rounded-full
                                            {{ $activity->event_type === 'escalated' ? 'bg-rose-500' : ($activity->event_type === 'claimed' ? 'bg-emerald-500' : 'bg-indigo-500') }}"></span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-slate-800 leading-snug">
                                            {{ $activity->description }}
                                        </p>
                                        <span class="text-[10px] text-slate-400 block mt-0.5">
                                            {{ $activity->created_at->format('M d, H:i:s') }}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="text-xs text-slate-400">No activity logged yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

        </div>

    </div>

</div>
@endsection

