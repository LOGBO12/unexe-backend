<?php

namespace Database\Seeders;

use App\Models\ForumTopic;
use App\Models\ForumReply;
use App\Models\User;
use Illuminate\Database\Seeder;

class ForumSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'super_admin')->first();

        if (!$admin) return;

        // Annonce de bienvenue
        $announcement = ForumTopic::create([
            'title'     => '🎓 Bienvenue sur la plateforme UNEXE !',
            'content'   => "Chers candidats,\n\nNous sommes ravis de vous accueillir sur la plateforme officielle du concours University Excellence Elite (UNEXE).\n\nCet espace communautaire vous permet de :\n- Consulter les annonces officielles du comité\n- Poser vos questions et obtenir des réponses\n- Échanger avec les autres candidats\n\nBonne chance à tous !",
            'type'      => 'announcement',
            'author_id' => $admin->id,
            'is_pinned' => true,
        ]);

        // Annonce du calendrier
        ForumTopic::create([
            'title'     => '📅 Calendrier des phases du concours',
            'content'   => "Voici le calendrier prévisionnel des phases :\n\n**Phase 1** — Défense de thème par département\n**Qualification 1** — Top 3 par département\n**Phase 2** — Présentation de projet + synthèse en anglais\n**Qualification 2** — Top 2 par département\n**Soirée de proclamation** — Remise des prix\n\nLes dates précises seront communiquées prochainement.",
            'type'      => 'announcement',
            'author_id' => $admin->id,
            'is_pinned' => true,
        ]);

        // Discussion exemple
        $discussion = ForumTopic::create([
            'title'     => 'Comment se préparer pour la Phase 1 ?',
            'content'   => "Bonjour à tous,\n\nJ'aimerais savoir comment le comité évalue les présentations de la Phase 1. Quels sont les critères principaux ?\n\nMerci d'avance !",
            'type'      => 'discussion',
            'author_id' => $admin->id,
        ]);

        // Réponse officielle à la discussion
        $reply = ForumReply::create([
            'topic_id'             => $discussion->id,
            'user_id'              => $admin->id,
            'content'              => "Bonne question ! Les critères d'évaluation sont :\n\n1. La maîtrise du sujet\n2. La clarté de la présentation\n3. La capacité à répondre aux questions\n4. Le respect du temps imparti\n\nBonne préparation à tous !",
            'is_official_response' => true,
        ]);
    }
}