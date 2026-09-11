<?php

namespace Database\Factories;

use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\CommandType;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Command>
 */
class CommandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'type' => CommandType::On,
            'payload' => null,
            'status' => CommandStatus::Pending,
            'issued_at' => now(),
        ];
    }

    public function type(CommandType $type, ?int $level = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'payload' => $type === CommandType::Dim ? ['level' => $level ?? $this->faker->numberBetween(10, 100)] : null,
        ]);
    }

    public function status(CommandStatus $status): static
    {
        return $this->state(fn (array $attributes) => match ($status) {
            CommandStatus::Sent => ['status' => $status, 'sent_at' => now()],
            CommandStatus::Acked => ['status' => $status, 'sent_at' => now(), 'acked_at' => now(), 'ack_code' => 0],
            CommandStatus::Failed => ['status' => $status, 'sent_at' => now()],
            CommandStatus::ConfirmedByTelemetry => ['status' => $status, 'sent_at' => now(), 'confirmed_at' => now()],
            CommandStatus::Unconfirmed => ['status' => $status, 'sent_at' => now(), 'reconcile_by' => now()->subMinute()],
            default => ['status' => $status],
        });
    }
}
