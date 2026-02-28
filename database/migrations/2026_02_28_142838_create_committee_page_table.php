<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_page', function (Blueprint $table) {
            $table->id();
            $table->text('project_description')->nullable();
            $table->text('vision')->nullable();
            $table->text('objectives')->nullable();  // JSON ou texte libre
            $table->string('team_photo')->nullable(); // photo d'ensemble du comité
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_page');
    }
};