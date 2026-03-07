<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_phases', function (Blueprint $table) {
            $table->id();
            $table->integer('phase_number');          // 1, 2, 3...
            $table->string('name');                   // "Phase 1 — Défense de thème"
            $table->text('description')->nullable();
            $table->integer('total_phases');          // nombre total de phases du concours
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            $table->boolean('is_final')->default(false); // true si c'est la dernière phase
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_phases');
    }
};