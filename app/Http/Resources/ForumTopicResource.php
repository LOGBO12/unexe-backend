<?php
// ForumTopicResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ForumTopicResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'content'       => $this->content,
            'type'          => $this->type,
            'is_pinned'     => $this->is_pinned,
            'is_closed'     => $this->is_closed,
            'replies_count' => $this->replies_count,
            'author'        => [
                'id'         => $this->author->id,
                'name'       => $this->author->name,
                'role'       => $this->author->role,
                'avatar_url' => $this->author->avatar
                    ? Storage::url($this->author->avatar)
                    : null,
            ],
            'created_at'    => $this->created_at->diffForHumans(),
            'created_at_raw' => $this->created_at,
        ];
    }
}