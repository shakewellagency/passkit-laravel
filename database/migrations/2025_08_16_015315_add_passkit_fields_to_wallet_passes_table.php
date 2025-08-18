<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallet_passes', function (Blueprint $table) {
            // PassKit stamp card specific fields
            $table->unsignedBigInteger('account_id')->nullable()->after('user_id');
            $table->string('pass_id')->nullable()->after('account_id');
            $table->string('pass_type')->nullable()->after('pass_id');
            $table->unsignedBigInteger('related_id')->nullable()->after('pass_type');
            $table->longText('pass_data')->nullable()->after('related_id');
            $table->string('status')->default('active')->after('pass_data');
            $table->json('device_registrations')->nullable()->after('status');
            $table->timestamp('deactivated_at')->nullable()->after('device_registrations');
            $table->string('deactivation_reason')->nullable()->after('deactivated_at');
            
            // Add indexes for performance
            $table->index(['pass_type', 'related_id'], 'wp_pass_type_related');
            $table->index(['account_id', 'status'], 'wp_account_status');
            $table->index('pass_id', 'wp_pass_id');
            $table->index(['user_id', 'pass_type'], 'wp_user_pass_type');
            
            // Add foreign key constraints
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_passes', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['account_id']);
            
            // Drop indexes
            $table->dropIndex('wp_pass_type_related');
            $table->dropIndex('wp_account_status');
            $table->dropIndex('wp_pass_id');
            $table->dropIndex('wp_user_pass_type');
            
            // Drop columns
            $table->dropColumn([
                'account_id',
                'pass_id',
                'pass_type',
                'related_id',
                'pass_data',
                'status',
                'device_registrations',
                'deactivated_at',
                'deactivation_reason'
            ]);
        });
    }
};