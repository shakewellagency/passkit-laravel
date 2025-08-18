<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkit_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id'); // Account association
            $table->date('date'); // Date of the analytics record
            $table->string('metric_type'); // Type of metric (installs, transactions, etc.)
            $table->string('metric_name'); // Specific metric name
            
            // Dimensional data
            $table->unsignedBigInteger('program_id')->nullable(); // Program association
            $table->string('tier_id')->nullable(); // Tier association
            $table->string('location_id')->nullable(); // Location/store ID
            $table->string('channel')->nullable(); // app, web, pos, etc.
            $table->string('device_type')->nullable(); // ios, android, web
            $table->string('country', 2)->nullable(); // ISO 3166-1 alpha-2
            $table->string('region')->nullable(); // State/province
            $table->string('city')->nullable();
            
            // Metric values
            $table->bigInteger('count_value')->default(0); // Count metrics
            $table->decimal('sum_value', 15, 2)->default(0); // Sum metrics (amounts, points)
            $table->decimal('avg_value', 15, 4)->nullable(); // Average metrics
            $table->decimal('min_value', 15, 2)->nullable(); // Minimum value
            $table->decimal('max_value', 15, 2)->nullable(); // Maximum value
            
            // Additional context
            $table->json('dimensions')->nullable(); // Additional dimensional data
            $table->json('metadata')->nullable(); // Extra metadata
            
            // Data freshness
            $table->datetime('calculated_at'); // When this record was calculated
            $table->datetime('data_updated_at')->nullable(); // When underlying data was last updated
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('program_id')->references('id')->on('pass_kit_programs')->onDelete('cascade');
            
            // Unique constraint to prevent duplicates
            $table->unique(['account_id', 'date', 'metric_type', 'metric_name', 'program_id', 'tier_id', 'location_id', 'channel'], 'passkit_analytics_unique');
            
            // Indexes for performance
            $table->index(['account_id', 'date', 'metric_type']);
            $table->index(['program_id', 'date']);
            $table->index(['metric_type', 'metric_name', 'date']);
            $table->index(['date', 'count_value']);
            $table->index(['location_id', 'date']);
            $table->index(['channel', 'date']);
            $table->index(['calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkit_analytics');
    }
};