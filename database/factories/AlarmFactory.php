<?php

namespace Database\Factories;

use App\Models\Alarm;
use App\Models\AlarmCode;
use App\Models\AlarmSeverity;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alarm>
 */
class AlarmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = $this->faker->randomElement(AlarmCode::cases());

        return [
            'device_id' => Device::factory(),
            'code' => $code,
            'severity' => $code->defaultSeverity(),
            'status' => 'open',
            'opened_at' => now(),
        ];
    }

    public function code(AlarmCode $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
            'severity' => $code->defaultSeverity(),
        ]);
    }

    public function severity(AlarmSeverity $severity): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => $severity,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }
}
