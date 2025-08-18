<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkit_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('passkit_transaction_id')->unique()->nullable(); // PassKit transaction ID
            $table->string('member_passkit_id'); // PassKit member ID
            $table->unsignedBigInteger('member_id')->nullable(); // Local member ID
            $table->unsignedBigInteger('account_id'); // Account association
            
            // Transaction details
            $table->enum('transaction_type', ['earn', 'burn', 'expire', 'adjustment', 'refund', 'bonus'])->default('earn');
            $table->integer('points_amount'); // Can be negative for burns
            $table->integer('points_balance_before');
            $table->integer('points_balance_after');
            
            // Transaction metadata
            $table->string('description')->nullable();
            $table->string('reference_id')->nullable(); // External reference (order ID, etc.)
            $table->string('source')->nullable(); // pos, app, web, api, etc.
            $table->string('category')->nullable(); // purchase, bonus, birthday, etc.
            
            // Location and context
            $table->string('location_id')->nullable();
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Purchase details (for earn transactions)
            $table->decimal('purchase_amount', 10, 2)->nullable();
            $table->string('currency', 3)->nullable(); // ISO 4217
            $table->decimal('points_multiplier', 5, 2)->nullable(); // e.g., 1.5x points
            
            // Expiry information
            $table->datetime('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->datetime('expired_at')->nullable();
            
            // Status and processing
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'reversed'])->default('completed');
            $table->string('failure_reason')->nullable();
            $table->datetime('processed_at')->nullable();
            
            // PassKit webhook data
            $table->json('webhook_data')->nullable(); // Raw webhook payload
            $table->json('passkit_data')->nullable(); // PassKit API response
            
            // Audit fields
            $table->string('created_by')->nullable(); // User or system that created transaction
            $table->string('reversed_by_transaction_id')->nullable(); // If this transaction was reversed
            $table->unsignedBigInteger('reversal_transaction_id')->nullable(); // Transaction that reversed this one
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('member_id')->references('id')->on('passkit_members')->onDelete('cascade');
            
            // Indexes for performance and reporting
            $table->index(['member_passkit_id', 'transaction_type']);
            $table->index(['account_id', 'created_at']);
            $table->index(['transaction_type', 'status']);
            $table->index(['reference_id']);
            $table->index(['processed_at']);
            $table->index(['expires_at', 'is_expired']);
            $table->index(['source', 'category']);
            $table->index(['location_id']);
            $table->index(['created_at', 'points_amount']); // For reporting
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkit_transactions');
    }
};