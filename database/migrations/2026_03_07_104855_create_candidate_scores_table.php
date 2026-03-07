<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('phase_id')->constrained('competition_phases')->onDelete('cascade');
            $table->decimal('score', 5, 2)->nullable();           // note /20
            $table->enum('status', ['pending', 'continuing', 'eliminated', 'leader'])
                  ->default('pending');
            $table->text('comment')->nullable();                   // commentaire du jury
            $table->foreignId('graded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            // Un candidat ne peut avoir qu'une seule note par phase
            $table->unique(['candidate_id', 'phase_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_scores');
    }
};