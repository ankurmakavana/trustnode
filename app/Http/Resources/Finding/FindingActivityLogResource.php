<?php

namespace App\Http\Resources\Finding;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'properties' => $this->properties,
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
            ],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
