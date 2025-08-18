<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkit_members', function (Blueprint $table) {
            $table->id();
            $table->string('passkit_id')->unique(); // PassKit member ID
            $table->string('external_id')->index(); // Your app's user identifier
            $table->unsignedBigInteger('user_id')->nullable(); // Local user ID
            $table->unsignedBigInteger('account_id'); // Account association
            $table->unsignedBigInteger('program_id'); // Program association
            $table->string('tier_id')->nullable(); // PassKit tier ID
            
            // Member information
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['M', 'F', 'O'])->nullable();
            
            // Address information
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable(); // ISO 3166-1 alpha-2
            
            // Points and rewards
            $table->integer('points_balance')->default(0);
            $table->integer('lifetime_points')->default(0);
            $table->integer('points_to_next_tier')->nullable();
            $table->decimal('tier_progress', 5, 2)->default(0); // Percentage
            
            // Member status
            $table->enum('status', ['active', 'inactive', 'suspended', 'expired'])->default('active');
            $table->enum('opt_in_status', ['opted_in', 'opted_out'])->default('opted_in');
            $table->boolean('email_opt_in')->default(true);
            $table->boolean('sms_opt_in')->default(false);
            $table->boolean('push_opt_in')->default(true);
            
            // Enrollment information
            $table->datetime('enrolled_at');
            $table->string('enrollment_channel')->nullable(); // app, web, pos, etc.
            $table->datetime('last_activity_at')->nullable();
            $table->datetime('tier_achieved_at')->nullable();
            
            // Preferences and custom data
            $table->json('preferences')->nullable(); // Member preferences
            $table->json('custom_fields')->nullable(); // Additional custom data
            $table->json('tags')->nullable(); // Member tags/categories
            
            // PassKit specific
            $table->json('passkit_data')->nullable(); // Raw PassKit member data
            $table->datetime('last_sync_at')->nullable(); // Last sync with PassKit
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('program_id')->references('id')->on('pass_kit_programs')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['account_id', 'status']);
            $table->index(['program_id', 'status']);
            $table->index(['external_id', 'account_id']);
            $table->index(['email']);
            $table->index(['enrolled_at']);
            $table->index(['last_activity_at']);
            $table->index(['points_balance']);
            $table->index(['tier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkit_members');
    }
};