<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_kit_programs', function (Blueprint $table) {
            $table->id();
            $table->string('passkit_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('program_type', ['membership', 'event_ticket', 'coupon'])->default('membership');
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['program_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_kit_programs');
    }
};