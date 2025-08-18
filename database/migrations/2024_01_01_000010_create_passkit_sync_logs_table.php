<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkit_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id'); // Account association
            
            // Sync operation details
            $table->enum('sync_type', ['full', 'incremental', 'member', 'transaction', 'notification', 'webhook'])->default('incremental');
            $table->enum('sync_direction', ['import', 'export', 'bidirectional'])->default('import');
            $table->enum('status', ['started', 'in_progress', 'completed', 'failed', 'cancelled'])->default('started');
            
            // Sync scope
            $table->unsignedBigInteger('program_id')->nullable(); // Program-specific sync
            $table->string('entity_type')->nullable(); // members, transactions, etc.
            $table->string('entity_id')->nullable(); // Specific entity ID
            
            // Progress tracking
            $table->integer('total_records')->default(0);
            $table->integer('processed_records')->default(0);
            $table->integer('successful_records')->default(0);
            $table->integer('failed_records')->default(0);
            $table->integer('skipped_records')->default(0);
            
            // Timing information
            $table->datetime('started_at');
            $table->datetime('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->decimal('records_per_second', 10, 2)->nullable();
            
            // Error handling
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable(); // Detailed error information
            $table->json('failed_record_ids')->nullable(); // IDs of failed records
            
            // Sync configuration
            $table->datetime('sync_from_date')->nullable(); // Start date for incremental sync
            $table->datetime('sync_to_date')->nullable(); // End date for sync
            $table->json('sync_filters')->nullable(); // Filters applied during sync
            $table->json('sync_options')->nullable(); // Additional sync options
            
            // API details
            $table->integer('api_requests_made')->default(0);
            $table->integer('api_requests_failed')->default(0);
            $table->decimal('avg_api_response_time_ms', 10, 3)->nullable();
            
            // Data changes
            $table->json('changes_summary')->nullable(); // Summary of changes made
            $table->longText('detailed_log')->nullable(); // Detailed operation log
            
            // Triggered by
            $table->string('triggered_by')->nullable(); // user_id or 'system'
            $table->string('trigger_source')->nullable(); // manual, cron, webhook, etc.
            
            // Next sync planning
            $table->datetime('next_sync_at')->nullable();
            $table->string('sync_frequency')->nullable(); // hourly, daily, weekly
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('program_id')->references('id')->on('pass_kit_programs')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['account_id', 'started_at']);
            $table->index(['sync_type', 'status']);
            $table->index(['program_id', 'started_at']);
            $table->index(['status', 'completed_at']);
            $table->index(['trigger_source', 'started_at']);
            $table->index(['next_sync_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkit_sync_logs');
    }
};