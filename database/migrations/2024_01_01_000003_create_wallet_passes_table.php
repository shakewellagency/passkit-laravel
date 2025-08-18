<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_passes', function (Blueprint $table) {
            $table->id();
            $table->string('passkit_id')->unique(); // PassKit pass ID
            $table->string('member_passkit_id')->nullable(); // Associated member ID
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('program_id')->nullable();
            
            // Pass content and data
            $table->json('pass_data'); // Core pass data (points, tier, etc.)
            $table->json('field_values')->nullable(); // Dynamic field values
            $table->json('barcode_data')->nullable(); // Barcode/QR code data
            
            // Installation and distribution
            $table->enum('status', ['active', 'inactive', 'suspended', 'expired', 'voided'])->default('active');
            $table->boolean('is_installed')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->json('install_urls')->nullable(); // Installation URLs for different platforms
            $table->json('qr_codes')->nullable(); // QR code data for installation
            
            // Device information
            $table->string('device_library_identifier')->nullable(); // Apple device identifier
            $table->string('push_token')->nullable(); // Device push token
            $table->enum('device_type', ['ios', 'android', 'web'])->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            
            // Pass lifecycle
            $table->datetime('issued_at'); // When the pass was issued
            $table->datetime('first_install_at')->nullable(); // First installation
            $table->datetime('last_updated_at')->nullable(); // Last content update
            $table->datetime('expires_at')->nullable(); // Pass expiration
            $table->datetime('voided_at')->nullable(); // When pass was voided
            $table->string('voided_reason')->nullable();
            
            // Location and relevance
            $table->json('relevant_locations')->nullable(); // Location-based relevance
            $table->json('relevant_beacons')->nullable(); // iBeacon relevance
            $table->datetime('relevant_date')->nullable(); // Time-based relevance
            
            // Usage tracking
            $table->integer('update_count')->default(0); // Number of updates
            $table->datetime('last_viewed_at')->nullable(); // Last time pass was viewed
            $table->integer('view_count')->default(0);
            $table->json('usage_analytics')->nullable(); // Detailed usage data
            
            // Sharing and distribution
            $table->boolean('sharable')->default(true);
            $table->integer('share_count')->default(0);
            $table->json('share_analytics')->nullable();
            
            // Synchronization
            $table->datetime('last_sync_at')->nullable(); // Last sync with PassKit
            $table->json('sync_metadata')->nullable(); // Sync-related data
            $table->boolean('sync_pending')->default(false);
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('template_id')->references('id')->on('card_templates')->onDelete('set null');
            $table->foreign('program_id')->references('id')->on('pass_kit_programs')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['account_id', 'status']);
            $table->index(['program_id', 'status']);
            $table->index(['member_passkit_id']);
            $table->index(['is_installed', 'status']);
            $table->index(['device_library_identifier']);
            $table->index(['issued_at']);
            $table->index(['expires_at', 'status']);
            $table->index(['last_sync_at', 'sync_pending']);
            $table->index(['device_type', 'os_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_passes');
    }
};