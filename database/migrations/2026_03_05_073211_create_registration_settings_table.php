<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_settings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('registration_deadline')->nullable();
            $table->boolean('registration_open')->default(true);
            $table->string('closed_message')->nullable()->default('Les inscriptions sont actuellement fermées.');
            $table->timestamps();
        });

        // Insérer la ligne par défaut (une seule ligne, toujours id=1)
        DB::table('registration_settings')->insert([
            'registration_open' => true,
            'registration_deadline' => null,
            'closed_message' => 'Les inscriptions sont actuellement fermées.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_settings');
    }
};