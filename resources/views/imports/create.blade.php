@extends('layouts.app', ['title' => 'Upload CSV - Support Ticket System'])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Upload CSV for Bulk Import</h1>
            <p class="text-sm text-slate-500 mt-1">Upload a large dataset of support tickets to process in the background</p>
        </div>
        <a href="{{ route('imports.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
            &larr; Back to Imports
        </a>
    </div>

    <!-- Upload Card -->
    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-xs space-y-6">

        <!-- Instructions & Sample Info -->
        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-700 space-y-2">
            <h3 class="font-bold text-slate-900 text-sm">CSV Format Specifications:</h3>
            <p>Your file must include the following header row:</p>
            <code class="block p-2 bg-white rounded border border-slate-200 font-mono text-[11px] text-indigo-700">
                subject,description,priority,category,customer_email
            </code>
            <ul class="list-disc list-inside space-y-1 text-slate-600">
                <li><strong>priority:</strong> low, medium, high, or critical</li>
                <li><strong>category:</strong> category slug (e.g. <code>technical-support</code>, <code>billing-invoicing</code>, <code>bug-report</code>)</li>
                <li><strong>customer_email:</strong> valid email address; creates customer account if not found</li>
                <li><strong>Streaming Engine:</strong> Memory-safe chunked reading supports files with tens of thousands of rows.</li>
            </ul>
            <div class="pt-2">
                <a href="{{ route('imports.sample') }}" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-800">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download Pre-formatted Sample CSV
                </a>
            </div>
        </div>

        <!-- Upload Form -->
        <form action="{{ route('imports.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">Select CSV File (Up to 50MB)</label>
                <input type="file" name="csv_file" accept=".csv,text/csv" required
                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition border border-slate-300 rounded-lg p-2">
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                <a href="{{ route('imports.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-xs transition">
                    Start Background Import
                </button>
            </div>
        </form>

    </div>

</div>
@endsection

