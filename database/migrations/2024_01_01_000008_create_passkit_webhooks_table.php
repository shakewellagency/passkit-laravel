<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkit_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id')->unique(); // Unique webhook identifier
            $table->string('event_type'); // PassKit event type
            $table->string('event_id')->nullable(); // PassKit event ID
            
            // Request details
            $table->string('source_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('headers')->nullable(); // Request headers
            $table->longText('payload'); // Raw webhook payload
            $table->string('signature')->nullable(); // Webhook signature for verification
            
            // Processing status
            $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'ignored'])->default('pending');
            $table->datetime('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->datetime('next_retry_at')->nullable();
            
            // Extracted data
            $table->string('member_passkit_id')->nullable(); // Extracted member ID
            $table->unsignedBigInteger('member_id')->nullable(); // Local member ID
            $table->unsignedBigInteger('account_id')->nullable(); // Account association
            $table->json('extracted_data')->nullable(); // Processed/extracted data
            
            // Related entities
            $table->unsignedBigInteger('transaction_id')->nullable(); // Created transaction
            $table->unsignedBigInteger('notification_id')->nullable(); // Created notification
            
            // Webhook validation
            $table->boolean('signature_valid')->nullable();
            $table->boolean('duplicate_event')->default(false);
            $table->string('idempotency_key')->nullable(); // For duplicate detection
            
            // Processing metadata
            $table->string('processor_version')->nullable(); // Version of webhook processor
            $table->json('processing_metadata')->nullable(); // Additional processing info
            $table->decimal('processing_time_ms', 10, 3)->nullable(); // Processing time in milliseconds
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('member_id')->references('id')->on('passkit_members')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('passkit_transactions')->onDelete('set null');
            $table->foreign('notification_id')->references('id')->on('passkit_notifications')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['event_type', 'status']);
            $table->index(['member_passkit_id']);
            $table->index(['account_id', 'created_at']);
            $table->index(['status', 'next_retry_at']); // For retry processing
            $table->index(['event_id']); // For duplicate detection
            $table->index(['idempotency_key']);
            $table->index(['processed_at']);
            $table->index(['created_at', 'event_type']); // For reporting
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkit_webhooks');
    }
};