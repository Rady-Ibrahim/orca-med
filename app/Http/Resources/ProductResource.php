<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'price' => $this->price,
            'company_id' => $this->company_id,
            'sales_count' => $this->whenCounted('sales'),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
