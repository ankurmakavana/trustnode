<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property User $resource
 */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'status' => $this->resource->status->value,
            'role' => $this->resource->role ? [
                'name' => $this->resource->role->name,
                'slug' => $this->resource->role->slug,
            ] : null,
            'last_login_at' => $this->resource->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->resource->last_login_ip,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
