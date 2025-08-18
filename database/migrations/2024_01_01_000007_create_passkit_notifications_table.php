<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkit_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('passkit_notification_id')->unique()->nullable(); // PassKit notification ID
            $table->string('member_passkit_id'); // PassKit member ID
            $table->unsignedBigInteger('member_id')->nullable(); // Local member ID
            $table->unsignedBigInteger('account_id'); // Account association
            
            // Notification content
            $table->string('title')->nullable();
            $table->text('message');
            $table->enum('notification_type', ['push', 'email', 'sms', 'in_app'])->default('push');
            $table->enum('category', ['points_earned', 'points_burned', 'tier_upgrade', 'promotion', 'expiry_warning', 'birthday', 'welcome', 'custom'])->default('custom');
            
            // Delivery details
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'cancelled'])->default('pending');
            $table->datetime('scheduled_at')->nullable(); // For scheduled notifications
            $table->datetime('sent_at')->nullable();
            $table->datetime('delivered_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            
            // Targeting and personalization
            $table->json('personalization_data')->nullable(); // Data for personalizing message
            $table->string('language', 5)->default('en'); // ISO 639-1 language code
            $table->string('timezone')->nullable();
            
            // Associated data
            $table->unsignedBigInteger('transaction_id')->nullable(); // Related transaction
            $table->string('campaign_id')->nullable(); // Marketing campaign ID
            $table->json('metadata')->nullable(); // Additional context data
            
            // Engagement tracking
            $table->boolean('opened')->default(false);
            $table->datetime('opened_at')->nullable();
            $table->boolean('clicked')->default(false);
            $table->datetime('clicked_at')->nullable();
            $table->string('click_url')->nullable();
            
            // PassKit specific
            $table->json('passkit_payload')->nullable(); // PassKit notification payload
            $table->json('passkit_response')->nullable(); // PassKit API response
            
            // Retry logic
            $table->integer('retry_count')->default(0);
            $table->datetime('next_retry_at')->nullable();
            $table->integer('max_retries')->default(3);
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('member_id')->references('id')->on('passkit_members')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('passkit_transactions')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['member_passkit_id', 'status']);
            $table->index(['account_id', 'created_at']);
            $table->index(['notification_type', 'status']);
            $table->index(['category', 'sent_at']);
            $table->index(['scheduled_at', 'status']);
            $table->index(['campaign_id']);
            $table->index(['status', 'next_retry_at']); // For retry processing
            $table->index(['sent_at', 'opened']); // For engagement reporting
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkit_notifications');
    }
};