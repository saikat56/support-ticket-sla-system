<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessCsvImportJob;
use App\Models\Category;
use App\Models\CsvImport;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketCsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::create([
            'name' => 'Admin CSV',
            'email' => 'admincsv@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
        ]);

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
        ]);
    }

    public function test_admin_can_upload_csv_and_job_is_dispatched(): void
    {
        Queue::fake();

        $content = "subject,description,priority,category,customer_email\n";
        $content .= "Database lag,High database latency,high,technical-support,customer@example.com\n";

        $file = UploadedFile::fake()->createWithContent('import.csv', $content);

        $response = $this->actingAs($this->admin)
            ->post(route('imports.store'), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect(route('imports.index'));

        $this->assertDatabaseHas('csv_imports', [
            'file_name' => 'import.csv',
            'status' => 'pending',
            'user_id' => $this->admin->id,
        ]);

        Queue::assertPushed(ProcessCsvImportJob::class);
    }

    public function test_process_csv_import_job_processes_valid_rows_and_records_invalid_rows(): void
    {
        $csvContent = "subject,description,priority,category,customer_email\n";
        // Valid row 1
        $csvContent .= "API rate limit error,Rate limit reached,high,technical-support,client1@example.com\n";
        // Invalid row 2 (invalid priority)
        $csvContent .= "Broken link,404 on docs,ultra-high,technical-support,client2@example.com\n";
        // Invalid row 3 (missing category)
        $csvContent .= "Missing Category,Cannot find category,low,non-existent-cat,client3@example.com\n";
        // Valid row 4
        $csvContent .= "SSL expiry,Certificate due soon,critical,technical-support,client4@example.com\n";

        $filePath = 'imports/test_import.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $import = CsvImport::create([
            'user_id' => $this->admin->id,
            'file_name' => 'test_import.csv',
            'status' => 'pending',
        ]);

        // Execute job synchronously
        $job = new ProcessCsvImportJob($import, $filePath);
        app()->call([$job, 'handle']);

        $import->refresh();

        $this->assertEquals('completed', $import->status);
        $this->assertEquals(4, $import->total_rows);
        $this->assertEquals(2, $import->successful_rows);
        $this->assertEquals(2, $import->failed_rows);

        // Verify the 2 valid tickets were inserted
        $this->assertDatabaseHas('tickets', [
            'subject' => 'API rate limit error',
            'priority' => TicketPriority::HIGH->value,
        ]);
        $this->assertDatabaseHas('tickets', [
            'subject' => 'SSL expiry',
            'priority' => TicketPriority::CRITICAL->value,
        ]);

        // Verify invalid rows logged
        $this->assertCount(2, $import->error_log);
        $this->assertEquals(3, $import->error_log[0]['row']); // Line 3 (1-indexed + header)
        $this->assertEquals(4, $import->error_log[1]['row']); // Line 4
    }

    public function test_can_download_sample_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('imports.sample'));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="tickets_import_sample.csv"');
    }
}

