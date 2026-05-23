<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'status' => $this->status?->value,
            'total_rows' => $this->total_rows,
            'success_count' => $this->success_count,
            'error_count' => $this->error_count,
            'duplicate_count' => $this->duplicate_count,
            'error_report_path' => $this->error_report_path,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'warehouse_id' => $this->warehouse_id,
            'uploader' => $this->whenLoaded('uploader', fn () => new UserResource($this->uploader)),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'errors_count' => $this->whenCounted('errors'),
        ];
    }
}
