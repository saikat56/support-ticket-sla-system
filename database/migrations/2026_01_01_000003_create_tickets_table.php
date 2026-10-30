<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->longText('description');
            $table->string('priority')->default('medium')->index(); // low, medium, high, critical
            $table->string('status')->default('open')->index(); // open, in_progress, waiting_customer, resolved, closed
            $table->string('sla_status')->default('within_sla')->index(); // within_sla, approaching, breached
            $table->boolean('is_escalated')->default(false)->index();
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();

            // Composite indexes for query efficiency and dashboard aggregates
            $table->index(['status', 'assigned_to_user_id']);
            $table->index(['sla_status', 'sla_due_at']);
            $table->index(['created_by_user_id', 'status']);
            $table->index(['priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

