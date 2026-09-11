<?php

namespace App\Http\Requests\Api;

use App\Models\CommandType;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCommandRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Single-operator session per PRD §3 (auth/multi-tenant out of
        // scope); the route's auth middleware is the actual gate.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Enum|list<\Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', Rule::exists('devices', 'id')],
            'type' => ['required', Rule::enum(CommandType::class)],
            'payload' => ['nullable', 'array'],
            'payload.level' => [
                'required_if:type,'.CommandType::Dim->value,
                'integer', 'between:0,100',
            ],
        ];
    }

    public function resolvedDevice(): Device
    {
        /** @var Device $device */
        $device = Device::query()->findOrFail($this->integer('device_id'));

        return $device;
    }

    public function commandType(): CommandType
    {
        /** @var CommandType $type */
        $type = $this->enum('type', CommandType::class);

        return $type;
    }

    /** @return array{level?: int}|null */
    public function payload(): ?array
    {
        $payload = $this->input('payload');

        if (! is_array($payload)) {
            return null;
        }

        return ['level' => (int) ($payload['level'] ?? 0)];
    }
}
