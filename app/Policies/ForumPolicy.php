<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ForumTopic;
use App\Models\ForumReply;

class ForumPolicy
{
    // Peut accéder au forum ?
    public function access(User $user): bool
    {
        return $user->canAccessForum();
    }

    // Peut créer une annonce ?
    public function createAnnouncement(User $user): bool
    {
        return $user->isComite();
    }

    // Peut épingler/fermer ?
    public function moderate(User $user): bool
    {
        return $user->isComite();
    }

    // Peut supprimer un topic ?
    public function deleteTopic(User $user, ForumTopic $topic): bool
    {
        return $user->isComite() || $topic->author_id === $user->id;
    }

    // Peut supprimer une réponse ?
    public function deleteReply(User $user, ForumReply $reply): bool
    {
        return $user->isComite() || $reply->user_id === $user->id;
    }

    // Peut marquer une réponse comme officielle ?
    public function markOfficial(User $user): bool
    {
        return $user->isComite();
    }
}