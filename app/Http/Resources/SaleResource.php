<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'sold_at' => $this->sold_at?->format('Y-m-d'),
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'pharmacy' => $this->whenLoaded('pharmacy', fn () => new PharmacyResource($this->pharmacy)),
            'province' => $this->whenLoaded('province', fn () => new ProvinceResource($this->province)),
        ];
    }
}
