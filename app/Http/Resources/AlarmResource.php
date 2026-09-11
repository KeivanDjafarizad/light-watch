<?php

namespace App\Http\Resources;

use App\Models\Alarm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Alarm */
class AlarmResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'device' => $this->whenLoaded('device', fn () => [
                'id' => $this->device->id,
                'external_id' => $this->device->external_id,
                'vendor' => $this->device->vendor->value,
                'label' => $this->device->label,
                'cabinet_code' => $this->device->cabinetCode(),
            ]),
            'code' => $this->code->value,
            'severity' => $this->severity->value,
            'status' => $this->status,
            'opened_at' => $this->opened_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
