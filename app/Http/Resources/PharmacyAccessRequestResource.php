<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyAccessRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'request_note' => $this->request_note,
            'admin_note' => $this->admin_note,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'requester' => $this->whenLoaded('requester', fn () => new UserResource($this->requester)),
        ];
    }
}
