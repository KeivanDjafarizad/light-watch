<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceReading;
use App\Models\SwitchState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceReading>
 */
class DeviceReadingFactory extends Factory
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
            'received_at' => now(),
            'device_reported_at' => now(),
            'power_w' => $this->faker->randomFloat(2, 0, 250),
            'energy_wh_cumulative' => $this->faker->randomFloat(2, 0, 100000),
            'energy_wh_delta' => $this->faker->randomFloat(2, 0, 10),
            'counter_reset_detected' => false,
            'switch_state' => SwitchState::On,
            'alarm_codes' => [],
            'raw_payload' => [],
            'dedup_key' => $this->faker->unique()->sha256(),
        ];
    }
}
