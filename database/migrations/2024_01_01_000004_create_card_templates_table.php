<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            $table->string('passkit_template_id')->unique()->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('template_type', ['membership', 'event_ticket', 'coupon', 'boarding_pass', 'store_card'])->default('membership');
            $table->unsignedBigInteger('account_id');
            $table->boolean('is_active')->default(true);
            
            // Template configuration
            $table->json('template_data')->nullable(); // PassKit template configuration
            $table->json('field_definitions')->nullable(); // Field mappings and definitions
            $table->json('design_settings')->nullable(); // Colors, fonts, layout
            $table->json('pass_settings')->nullable(); // Pass-specific settings
            
            // PassKit API fields
            $table->string('timezone')->default('America/Los_Angeles');
            $table->string('protocol')->nullable();
            $table->json('locations')->nullable(); // Geo-locations for relevance
            $table->json('beacons')->nullable(); // iBeacon data
            $table->datetime('expiry_date')->nullable();
            
            // Distribution settings
            $table->boolean('allow_sharing')->default(true);
            $table->boolean('voided')->default(false);
            $table->integer('max_distance')->nullable(); // For location-based relevance
            
            // Audit fields
            $table->timestamp('last_used_at')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            // Indexes for performance
            $table->index(['account_id', 'is_active']);
            $table->index(['template_type', 'is_active']);
            $table->index(['passkit_template_id']);
            $table->index(['last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_templates');
    }
};