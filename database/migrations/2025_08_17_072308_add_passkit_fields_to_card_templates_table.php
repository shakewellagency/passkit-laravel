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
        Schema::table('card_templates', function (Blueprint $table) {
            // PassKit integration fields
            // Note: passkit_template_id and its index already exist on the base
            // create_card_templates_table migration; they are intentionally not re-added here.
            $table->string('passkit_program_id')->nullable()->after('passkit_template_id');
            $table->string('passkit_tier_id')->nullable()->after('passkit_program_id');
            $table->json('passkit_metadata')->nullable()->after('passkit_tier_id');
            $table->timestamp('passkit_synced_at')->nullable()->after('passkit_metadata');
            $table->text('passkit_sync_error')->nullable()->after('passkit_synced_at');
            $table->boolean('passkit_enabled')->default(true)->after('passkit_sync_error');

            // Indexes for PassKit lookups
            $table->index(['passkit_enabled', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_templates', function (Blueprint $table) {
            $table->dropIndex('card_templates_passkit_enabled_is_active_index');

            // Leave passkit_template_id — owned by the base create_card_templates_table migration.
            $table->dropColumn([
                'passkit_program_id',
                'passkit_tier_id',
                'passkit_metadata',
                'passkit_synced_at',
                'passkit_sync_error',
                'passkit_enabled',
            ]);
        });
    }
};
