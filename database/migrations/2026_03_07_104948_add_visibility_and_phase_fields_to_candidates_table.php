<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            // true = visible sur la page publique, false = éliminé ou profil bloqué
            $table->boolean('is_visible')->default(true)->after('status');
            // Phase actuelle du candidat (1 = phase 1, etc.)
            $table->integer('current_phase')->default(1)->after('is_visible');
            // Leader final du concours
            $table->boolean('is_leader')->default(false)->after('current_phase');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'current_phase', 'is_leader']);
        });
    }
};