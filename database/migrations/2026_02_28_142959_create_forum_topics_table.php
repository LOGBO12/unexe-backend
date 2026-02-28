<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_topics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['announcement', 'discussion'])->default('discussion');
            // announcement = publié par comité uniquement
            // discussion = ouvert à tous
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_pinned')->default(false);   // épinglé en haut
            $table->boolean('is_closed')->default(false);   // fermé aux réponses
            $table->integer('replies_count')->default(0);   // compteur pour perf
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_topics');
    }
};