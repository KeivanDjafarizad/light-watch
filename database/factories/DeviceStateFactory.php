<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceState>
 */
class DeviceStateFactory extends Factory
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
            'last_energy_wh_cumulative' => $this->faker->randomFloat(2, 0, 100000),
            'last_received_at' => now(),
            'is_online' => true,
            'last_seen_at' => now(),
            'last_power_w' => $this->faker->randomFloat(2, 0, 250),
        ];
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => false,
        ]);
    }
}
