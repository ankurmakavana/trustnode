<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property UserInvitation $resource
 */
final class UserInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'email' => $this->resource->email,
            'role' => $this->resource->role ? [
                'name' => $this->resource->role->name,
                'slug' => $this->resource->role->slug,
            ] : null,
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
            'accepted_at' => $this->resource->accepted_at?->toIso8601String(),
            'revoked_at' => $this->resource->revoked_at?->toIso8601String(),
        ];
    }
}
