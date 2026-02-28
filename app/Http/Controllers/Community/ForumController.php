<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\ForumTopic;
use App\Models\ForumReply;
use App\Models\ActionLog;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    // ==================== TOPICS ====================

    // Liste des topics (annonces + discussions)
    public function index(Request $request)
    {
        $query = ForumTopic::with('author')
            ->withCount('replies')
            ->orderBy('is_pinned', 'desc')   // épinglés en premier
            ->orderBy('created_at', 'desc');

        // Filtre par type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $topics = $query->paginate(15);

        // Stats du forum
        $stats = [
            'total_topics'        => ForumTopic::count(),
            'total_announcements' => ForumTopic::announcements()->count(),
            'total_discussions'   => ForumTopic::discussions()->count(),
            'total_replies'       => ForumReply::count(),
        ];

        return response()->json([
            'topics' => $topics,
            'stats'  => $stats,
        ]);
    }

    // Voir un topic avec ses réponses
    public function show(int $id)
    {
        $topic = ForumTopic::with('author')->findOrFail($id);

        // Récupérer les réponses de premier niveau avec leurs sous-réponses
        $replies = ForumReply::with(['user', 'children.user'])
            ->where('topic_id', $id)
            ->whereNull('parent_id')  // seulement les réponses racines
            ->orderBy('is_official_response', 'desc') // réponses officielles en premier
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'topic'   => $topic,
            'replies' => $replies,
        ]);
    }

    // Créer un topic (discussion — ouvert à tous)
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'   => 'required|string|min:5|max:255',
            'content' => 'required|string|min:10',
        ]);

        // Les candidats ne peuvent créer que des discussions
        $topic = ForumTopic::create([
            'title'     => $data['title'],
            'content'   => $data['content'],
            'type'      => 'discussion',
            'author_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Discussion créée avec succès.',
            'topic'   => $topic->load('author'),
        ], 201);
    }

    // Créer une annonce (comité uniquement)
    public function storeAnnouncement(Request $request)
    {
        $data = $request->validate([
            'title'   => 'required|string|min:5|max:255',
            'content' => 'required|string|min:10',
        ]);

        $topic = ForumTopic::create([
            'title'     => $data['title'],
            'content'   => $data['content'],
            'type'      => 'announcement',
            'author_id' => $request->user()->id,
            'is_pinned' => true, // les annonces sont épinglées par défaut
        ]);

        ActionLog::log(
            $request->user(),
            'publish_announcement',
            $topic,
            ['title' => $data['title']]
        );

        return response()->json([
            'message' => 'Annonce publiée avec succès.',
            'topic'   => $topic->load('author'),
        ], 201);
    }

    // Supprimer un topic
    public function destroy(Request $request, int $id)
    {
        $topic = ForumTopic::findOrFail($id);
        $user  = $request->user();

        // Seul l'auteur ou un membre du comité peut supprimer
        if ($topic->author_id !== $user->id && !$user->isComite()) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à supprimer ce topic.'
            ], 403);
        }

        $topic->delete(); // supprime aussi les replies (cascade)

        return response()->json(['message' => 'Topic supprimé.']);
    }

    // ==================== ÉPINGLER / FERMER (Comité) ====================

    // Épingler / désépingler un topic
    public function pin(int $id)
    {
        $topic = ForumTopic::findOrFail($id);
        $topic->update(['is_pinned' => !$topic->is_pinned]);

        $status = $topic->is_pinned ? 'épinglé' : 'désépinglé';

        return response()->json([
            'message' => "Topic {$status}.",
            'topic'   => $topic,
        ]);
    }

    // Ouvrir / fermer un topic aux réponses
    public function close(int $id)
    {
        $topic = ForumTopic::findOrFail($id);
        $topic->update(['is_closed' => !$topic->is_closed]);

        $status = $topic->is_closed ? 'fermé' : 'rouvert';

        return response()->json([
            'message' => "Topic {$status}.",
            'topic'   => $topic,
        ]);
    }

    // ==================== REPLIES ====================

    // Ajouter une réponse à un topic
    public function storeReply(Request $request, int $id)
    {
        $topic = ForumTopic::findOrFail($id);

        // Vérifier que le topic n'est pas fermé
        if ($topic->is_closed) {
            return response()->json([
                'message' => 'Ce topic est fermé. Impossible d\'ajouter une réponse.'
            ], 422);
        }

        $data = $request->validate([
            'content'   => 'required|string|min:2',
            'parent_id' => 'nullable|exists:forum_replies,id',
        ]);

        // Vérifier que le parent_id appartient bien à ce topic
        if (!empty($data['parent_id'])) {
            $parent = ForumReply::findOrFail($data['parent_id']);
            if ($parent->topic_id !== $topic->id) {
                return response()->json([
                    'message' => 'Réponse parente invalide.'
                ], 422);
            }
        }

        $reply = ForumReply::create([
            'topic_id'             => $id,
            'user_id'              => $request->user()->id,
            'content'              => $data['content'],
            'parent_id'            => $data['parent_id'] ?? null,
            'is_official_response' => false,
        ]);

        return response()->json([
            'message' => 'Réponse ajoutée.',
            'reply'   => $reply->load('user', 'children.user'),
        ], 201);
    }

    // Supprimer une réponse
    public function destroyReply(Request $request, int $id)
    {
        $reply = ForumReply::findOrFail($id);
        $user  = $request->user();

        // Seul l'auteur ou un membre du comité peut supprimer
        if ($reply->user_id !== $user->id && !$user->isComite()) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette réponse.'
            ], 403);
        }

        $reply->delete(); // supprime aussi les sous-réponses (cascade)

        return response()->json(['message' => 'Réponse supprimée.']);
    }

    // Marquer une réponse comme officielle (comité uniquement)
    public function markOfficial(Request $request, int $id)
    {
        $reply = ForumReply::findOrFail($id);
        $reply->update([
            'is_official_response' => !$reply->is_official_response
        ]);

        $status = $reply->is_official_response ? 'marquée officielle' : 'démarquée';

        ActionLog::log(
            $request->user(),
            'mark_official_reply',
            $reply,
            ['topic_id' => $reply->topic_id]
        );

        return response()->json([
            'message' => "Réponse {$status}.",
            'reply'   => $reply->load('user'),
        ]);
    }
}