<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'company_id' => $this->company_id,
            'warehouse_id' => $this->warehouse_id,
            'sensitive_unlock_expires_at' => $this->sensitive_unlock_expires_at?->toIso8601String(),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'type' => $this->warehouse->type?->value,
            ]),
        ];
    }
}
