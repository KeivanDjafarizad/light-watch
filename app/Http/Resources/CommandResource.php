<?php

namespace App\Http\Resources;

use App\Models\Command;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Command */
class CommandResource extends JsonResource
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
            'type' => $this->type->value,
            'payload' => $this->payload,
            'status' => $this->status->value,
            'issued_at' => $this->issued_at->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'acked_at' => $this->acked_at?->toIso8601String(),
            'ack_code' => $this->ack_code,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'reconcile_by' => $this->reconcile_by?->toIso8601String(),
        ];
    }
}
