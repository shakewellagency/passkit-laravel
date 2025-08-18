<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_kit_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('passkit_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('program_id');
            $table->string('template_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('pass_kit_programs')->onDelete('cascade');
            $table->index(['program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_kit_tiers');
    }
};