<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'phone' => $this->phone,
            'identity_number' => $this->identity_number,
            'email' => $this->email,
            'role_ids' => $this->whenLoaded('roles', fn () => $this->roles->pluck('id')),
            'roles' => $this->whenLoaded('roles'),
            'collection_route_id' => $this->collection_route_id,
            'collection_route' => $this->whenLoaded('collectionRoute'),
            'route_ids' => $this->whenLoaded('collectionRoutes', fn () => $this->collectionRoutes->pluck('id')),
            'collection_routes' => $this->whenLoaded('collectionRoutes'),
            'address' => $this->address,
            'is_active' => $this->is_active,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,
            'created_at' => $this->created_at,
        ];
    }
}
