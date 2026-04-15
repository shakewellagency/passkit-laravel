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
            // Note: account_id, pass_data, status already exist on the base
            // create_wallet_passes_table migration; they are intentionally not re-added here.
            $table->string('pass_id')->nullable()->after('account_id');
            $table->string('pass_type')->nullable()->after('pass_id');
            $table->unsignedBigInteger('related_id')->nullable()->after('pass_type');
            $table->json('device_registrations')->nullable()->after('status');
            $table->timestamp('deactivated_at')->nullable()->after('device_registrations');
            $table->string('deactivation_reason')->nullable()->after('deactivated_at');

            // Add indexes for performance
            $table->index(['pass_type', 'related_id'], 'wp_pass_type_related');
            $table->index('pass_id', 'wp_pass_id');
            $table->index(['user_id', 'pass_type'], 'wp_user_pass_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_passes', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('wp_pass_type_related');
            $table->dropIndex('wp_pass_id');
            $table->dropIndex('wp_user_pass_type');

            // Drop columns (leave account_id, pass_data, status — owned by base migration)
            $table->dropColumn([
                'pass_id',
                'pass_type',
                'related_id',
                'device_registrations',
                'deactivated_at',
                'deactivation_reason',
            ]);
        });
    }
};