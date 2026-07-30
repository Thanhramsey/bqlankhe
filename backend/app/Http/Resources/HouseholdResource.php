<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'route' => $this->whenLoaded('route'),
            'services' => $this->whenLoaded('services'),
            'service_id' => $this->whenLoaded('services', fn () => $this->services->first()?->service_id),
            'service_started_at' => $this->whenLoaded('services', fn () => $this->services->first()?->started_at?->format('Y-m-d')),
        ];
    }
}
