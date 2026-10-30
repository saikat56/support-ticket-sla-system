@extends('layouts.app', ['title' => 'Create Support Ticket'])

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Create Support Ticket</h1>
            <p class="text-sm text-slate-500 mt-1">Please provide clear details so our engineering team can address your request within our SLA window.</p>
        </div>
        <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 flex items-center">
            &larr; Back to Tickets
        </a>
    </div>

    <!-- Ticket Form -->
    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-xs">
        <form action="{{ route('tickets.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Subject -->
            <div>
                <label for="subject" class="block text-sm font-semibold text-slate-800">Ticket Subject <span class="text-red-500">*</span></label>
                <input type="text" name="subject" id="subject" required value="{{ old('subject') }}" placeholder="Brief summary of the issue or request..."
                       class="mt-1 block w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm shadow-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <!-- Category & Priority Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Category -->
                <div>
                    <label for="category_id" class="block text-sm font-semibold text-slate-800">Category <span class="text-red-500">*</span></label>
                    <select name="category_id" id="category_id" required
                            class="mt-1 block w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm shadow-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select a category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Priority with SLA hours reminder -->
                <div x-data="{ priority: '{{ old('priority', 'medium') }}' }">
                    <label for="priority" class="block text-sm font-semibold text-slate-800">Priority Level <span class="text-red-500">*</span></label>
                    <select name="priority" id="priority" x-model="priority" required
                            class="mt-1 block w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm shadow-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach ($priorities as $p)
                            <option value="{{ $p->value }}" {{ old('priority', 'medium') === $p->value ? 'selected' : '' }}>
                                {{ $p->label() }}
                            </option>
                        @endforeach
                    </select>

                    <!-- Dynamic SLA Notice -->
                    <div class="mt-2 text-xs font-medium text-slate-500 flex items-center">
                        <svg class="w-3.5 h-3.5 mr-1 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-show="priority === 'critical'" class="text-red-700 font-bold">Resolution SLA: 2 Business Hours (Highest urgency)</span>
                        <span x-show="priority === 'high'" class="text-amber-700 font-semibold">Resolution SLA: 8 Business Hours (High priority)</span>
                        <span x-show="priority === 'medium'" class="text-blue-700">Resolution SLA: 24 Business Hours (Standard)</span>
                        <span x-show="priority === 'low'" class="text-slate-600">Resolution SLA: 48 Business Hours (Low priority)</span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-semibold text-slate-800">Description & Reproduction Details <span class="text-red-500">*</span></label>
                <textarea name="description" id="description" rows="6" required placeholder="Please provide detailed steps to reproduce, error codes, log extracts, or specific impact..."
                          class="mt-1 block w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm shadow-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description') }}</textarea>
            </div>

            <!-- File Attachments -->
            <div>
                <label class="block text-sm font-semibold text-slate-800">Attachments (Optional)</label>
                <p class="text-xs text-slate-500 mb-2">Upload logs, screenshots, or error traces (PNG, JPG, PDF, ZIP, TXT, CSV up to 10MB per file).</p>
                <input type="file" name="attachments[]" multiple
                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition">
            </div>

            <!-- Business Hours SLA Info Banner -->
            <div class="p-4 bg-indigo-50/60 rounded-xl border border-indigo-100 text-xs text-indigo-900 flex items-start space-x-2">
                <svg class="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <span class="font-bold">Business Hours SLA Policy:</span>
                    SLA clocks run during active working hours: <strong>Sunday through Thursday, 9:00 AM – 6:00 PM</strong>.
                    Tickets submitted outside working hours or over weekends will start counting from the next business opening window.
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                <a href="{{ route('tickets.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-xs transition">
                    Submit Ticket
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

