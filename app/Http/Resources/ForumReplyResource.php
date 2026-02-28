<?php
// ForumReplyResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ForumReplyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'content'               => $this->content,
            'is_official_response'  => $this->is_official_response,
            'parent_id'             => $this->parent_id,
            'user'                  => [
                'id'         => $this->user->id,
                'name'       => $this->user->name,
                'role'       => $this->user->role,
                'avatar_url' => $this->user->avatar
                    ? Storage::url($this->user->avatar)
                    : null,
            ],
            'children'   => ForumReplyResource::collection(
                $this->whenLoaded('children')
            ),
            'created_at' => $this->created_at->diffForHumans(),
            'created_at_raw' => $this->created_at,
        ];
    }
}