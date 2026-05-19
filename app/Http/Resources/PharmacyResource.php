<?php

namespace App\Http\Resources;

use App\Services\PharmacyAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyResource extends JsonResource
{
    public static int $maskCounter = 0;

    public function toArray(Request $request): array
    {
        $mask = (bool) $request->attributes->get('mask_pharmacies', false);
        $index = ++self::$maskCounter;

        /** @var PharmacyAccessService $accessService */
        $accessService = app(PharmacyAccessService::class);

        return [
            'id' => $this->id,
            'name' => $mask
                ? $accessService->maskPharmacyName($index)
                : $this->name,
            'phone' => $mask ? null : $this->phone,
            'address' => $mask ? null : $this->address,
            'supplier_id' => $this->supplier_id,
            'province_id' => $this->province_id,
            'is_masked' => $mask,
            'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($this->supplier)),
            'province' => $this->whenLoaded('province', fn () => new ProvinceResource($this->province)),
        ];
    }
}
