<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The base passkit_members migration (2024_01_01_000005) was edited in
 * place after v1 to declare a sync_pending column. Host apps that ran
 * migrations before that edit still don't have the column, because
 * Laravel won't re-run a migration that's already in `migrations`.
 *
 * v2.0.0 of PassKitSyncService writes to passkit_members.sync_pending
 * on every member sync (and reads it in getSyncStatus), so the missing
 * column makes the cron explode with:
 *
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'sync_pending'
 *
 * This migration is idempotent — fresh installs already have the column
 * via the base migration, so we hasColumn-guard before touching it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('passkit_members', 'sync_pending')) {
            return;
        }

        Schema::table('passkit_members', function (Blueprint $table) {
            $table->boolean('sync_pending')->default(false)->after('last_sync_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('passkit_members', 'sync_pending')) {
            return;
        }

        Schema::table('passkit_members', function (Blueprint $table) {
            $table->dropColumn('sync_pending');
        });
    }
};
