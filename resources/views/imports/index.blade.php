@extends('layouts.app', ['title' => 'CSV Imports - Support Ticket System'])

@section('content')
<div class="space-y-6">

    <!-- Header & Action Row -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">CSV Ticket Imports</h1>
            <p class="text-sm text-slate-500 mt-1">Asynchronously process bulk ticket files with memory-safe streaming validation</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('imports.sample') }}" class="px-3.5 py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition shadow-xs flex items-center">
                <svg class="w-4 h-4 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Download Sample CSV
            </a>
            <a href="{{ route('imports.create') }}" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-xs transition flex items-center">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Upload New CSV
            </a>
        </div>
    </div>

    <!-- Imports Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="py-3.5 px-6 font-semibold">Import ID</th>
                        <th class="py-3.5 px-4 font-semibold">File Name</th>
                        <th class="py-3.5 px-4 font-semibold">Imported By</th>
                        <th class="py-3.5 px-4 font-semibold text-center">Total Rows</th>
                        <th class="py-3.5 px-4 font-semibold text-center">Successful</th>
                        <th class="py-3.5 px-4 font-semibold text-center">Failed</th>
                        <th class="py-3.5 px-4 font-semibold text-center">Status</th>
                        <th class="py-3.5 px-4 font-semibold text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($imports as $import)
                        <tr class="hover:bg-slate-50 transition" x-data="{ expanded: false }">
                            <td class="py-4 px-6 font-mono font-bold text-slate-700">
                                #{{ $import->id }}
                            </td>
                            <td class="py-4 px-4 font-medium text-slate-900">
                                {{ $import->file_name }}
                            </td>
                            <td class="py-4 px-4 text-slate-700">
                                {{ $import->user->name }}
                            </td>
                            <td class="py-4 px-4 text-center font-semibold text-slate-800">
                                {{ $import->total_rows }}
                            </td>
                            <td class="py-4 px-4 text-center font-bold text-emerald-600">
                                {{ $import->successful_rows }}
                            </td>
                            <td class="py-4 px-4 text-center">
                                @if($import->failed_rows > 0)
                                    <button @click="expanded = !expanded" class="px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 hover:bg-rose-200 transition">
                                        {{ $import->failed_rows }} failed (view)
                                    </button>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                    {{ $import->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($import->status === 'processing' ? 'bg-indigo-100 text-indigo-800 animate-pulse' : ($import->status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700')) }}">
                                    {{ ucfirst($import->status) }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right text-xs text-slate-500 whitespace-nowrap">
                                {{ $import->created_at->diffForHumans() }}
                            </td>
                        </tr>

                        <!-- Expandable Error Details Row -->
                        @if(!empty($import->error_log))
                            <tr x-show="expanded" x-cloak class="bg-rose-50/50">
                                <td colspan="8" class="p-4 border-t border-rose-100 text-xs">
                                    <h4 class="font-bold text-rose-900 mb-2">Import Failure Diagnostic Log:</h4>
                                    <div class="max-h-48 overflow-y-auto space-y-2 bg-white p-3 rounded-lg border border-rose-200">
                                        @foreach ($import->error_log as $err)
                                            <div class="text-slate-700">
                                                <strong class="text-rose-700">Row {{ $err['row'] ?? '?' }}:</strong>
                                                {{ implode(', ', $err['errors'] ?? []) }}
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                No CSV imports have been run yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($imports->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $imports->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

