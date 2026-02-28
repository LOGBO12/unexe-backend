<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action');
            // Exemples d'actions :
            // 'validate_candidate', 'reject_candidate'
            // 'invite_comite', 'invite_candidat'
            // 'update_committee_page', 'publish_announcement'
            $table->string('target_type')->nullable(); // ex: 'App\Models\Candidate'
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('details')->nullable(); // infos supplémentaires libres
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_logs');
    }
};