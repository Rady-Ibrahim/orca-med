<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'is_active' => $this->is_active,
            'products_count' => $this->whenCounted('products'),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
