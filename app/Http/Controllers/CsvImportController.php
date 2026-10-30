<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCsvImportJob;
use App\Models\CsvImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportController extends Controller
{
    public function index(Request $request): View
    {
        $imports = CsvImport::with('user')->latest()->paginate(10);

        return view('imports.index', compact('imports'));
    }

    public function create(): View
    {
        return view('imports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'], // max 50MB
        ]);

        $file = $request->file('csv_file');
        $fileName = $file->getClientOriginalName();
        $storedPath = $file->store('imports', 'local');

        $import = CsvImport::create([
            'user_id' => $request->user()->id,
            'file_name' => $fileName,
            'status' => 'pending',
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ]);

        // Dispatch background processing job onto the Redis queue
        ProcessCsvImportJob::dispatch($import, $storedPath);

        return redirect()->route('imports.index')
            ->with('status', 'CSV import queued for background processing. You can monitor the progress on this page.');
    }

    /**
     * Download a valid CSV sample template.
     */
    public function downloadSample(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="tickets_import_sample.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['subject', 'description', 'priority', 'category', 'customer_email']);

            fputcsv($handle, [
                'Database replica lag alert',
                'Replica database lag exceeded 60 seconds threshold.',
                'critical',
                'technical-support',
                'customer1@example.com',
            ]);

            fputcsv($handle, [
                'Need duplicate VAT invoice for Q3',
                'Please send a copy of the VAT invoice for September.',
                'low',
                'billing-invoicing',
                'customer2@example.com',
            ]);

            fputcsv($handle, [
                'SSO SAML login timeout',
                'Users logging in via Okta experience 504 Gateway Timeout intermittently.',
                'high',
                'technical-support',
                'customer1@example.com',
            ]);

            fclose($handle);
        }, 200, $headers);
    }
}

