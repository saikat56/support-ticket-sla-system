<?php

namespace App\Jobs;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CsvImport;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaCalculatorService;
use App\Services\TicketActivityLogger;
use App\Services\TicketIdGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use SplFileObject;

class ProcessCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes timeout for very large files

    public function __construct(
        public CsvImport $import,
        public string $filePath
    ) {}

    public function handle(
        SlaCalculatorService $slaCalculator,
        TicketIdGenerator $idGenerator,
        TicketActivityLogger $activityLogger
    ): void {
        $this->import->update(['status' => 'processing']);

        $fullPath = Storage::disk('local')->path($this->filePath);
        if (! file_exists($fullPath)) {
            $this->import->update([
                'status' => 'failed',
                'error_log' => [['row' => 0, 'errors' => ['File not found on server.']]],
            ]);
            return;
        }

        $file = new SplFileObject($fullPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = null;
        $totalRows = 0;
        $successRows = 0;
        $failedRows = 0;
        $errorLog = [];

        // Preload categories by name and slug to minimize database roundtrips
        $categories = Category::all()->keyBy(fn($c) => strtolower($c->slug));

        $batch = [];
        $batchSize = 100;

        foreach ($file as $index => $row) {
            if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            // Header row
            if ($headers === null) {
                $headers = array_map(fn($h) => trim(strtolower($h)), $row);
                continue;
            }

            $totalRows++;
            $rowNumber = $index + 1; // 1-based indexing including header

            if (count($row) !== count($headers)) {
                $failedRows++;
                $errorLog[] = [
                    'row' => $rowNumber,
                    'errors' => ['Column count does not match header row.'],
                    'data' => $row,
                ];
                continue;
            }

            $rowData = array_combine($headers, array_map('trim', $row));

            // Validate row data
            $validator = Validator::make($rowData, [
                'subject' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
                'priority' => ['required', 'in:low,medium,high,critical'],
                'category' => ['required', 'string'],
                'customer_email' => ['required', 'email'],
            ]);

            if ($validator->fails()) {
                $failedRows++;
                $errorLog[] = [
                    'row' => $rowNumber,
                    'errors' => $validator->errors()->all(),
                    'data' => $rowData,
                ];
                continue;
            }

            // Resolve category
            $categoryKey = strtolower($rowData['category']);
            $category = $categories->get($categoryKey) ?? Category::where('name', 'like', $rowData['category'])->first();

            if (! $category) {
                $failedRows++;
                $errorLog[] = [
                    'row' => $rowNumber,
                    'errors' => ["Category '{$rowData['category']}' not found."],
                    'data' => $rowData,
                ];
                continue;
            }

            // Resolve or create Customer user
            $customerEmail = strtolower($rowData['customer_email']);
            $customer = User::firstOrCreate(
                ['email' => $customerEmail],
                [
                    'name' => ucwords(strstr($customerEmail, '@', true) ?: 'Customer'),
                    'password' => Hash::make('password'),
                    'role' => UserRole::CUSTOMER,
                    'is_active' => true,
                ]
            );

            // Compute priority and SLA
            $priority = TicketPriority::from(strtolower($rowData['priority']));
            $createdAt = now();
            $slaDueAt = $slaCalculator->calculateDueDate($priority, $createdAt);
            $ticketNumber = $idGenerator->generate();

            // Insert ticket
            try {
                DB::transaction(function () use (
                    $ticketNumber,
                    $rowData,
                    $priority,
                    $slaDueAt,
                    $category,
                    $customer,
                    $createdAt,
                    $activityLogger
                ) {
                    $ticket = Ticket::create([
                        'ticket_number' => $ticketNumber,
                        'subject' => $rowData['subject'],
                        'description' => $rowData['description'],
                        'priority' => $priority,
                        'status' => TicketStatus::OPEN,
                        'sla_due_at' => $slaDueAt,
                        'category_id' => $category->id,
                        'created_by_user_id' => $customer->id,
                        'created_at' => $createdAt,
                    ]);

                    $activityLogger->log(
                        $ticket,
                        'created',
                        "Ticket imported from CSV by {$this->import->user->name}",
                        $this->import->user,
                        ['import_id' => $this->import->id]
                    );
                });

                $successRows++;
            } catch (\Throwable $e) {
                $failedRows++;
                $errorLog[] = [
                    'row' => $rowNumber,
                    'errors' => [$e->getMessage()],
                    'data' => $rowData,
                ];
            }

            // Update intermediate progress periodically every 100 rows
            if ($totalRows % 100 === 0) {
                $this->import->update([
                    'total_rows' => $totalRows,
                    'successful_rows' => $successRows,
                    'failed_rows' => $failedRows,
                ]);
            }
        }

        // Finalize import record
        $this->import->update([
            'total_rows' => $totalRows,
            'successful_rows' => $successRows,
            'failed_rows' => $failedRows,
            'status' => 'completed',
            'error_log' => $errorLog,
        ]);

        // Clean up temporary CSV file
        Storage::disk('local')->delete($this->filePath);
    }
}

