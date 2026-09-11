<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Granularity;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => 'LUM-'.str_pad(strtoupper(dechex($this->faker->unique()->numberBetween(0, 0xFFFFFF))), 6, '0', STR_PAD_LEFT),
            'vendor' => Vendor::LuminaP2P,
            'granularity' => Granularity::Point,
            'label' => $this->faker->optional()->word(),
        ];
    }

    public function cp3000(): static
    {
        return $this->state(fn (array $attributes) => [
            'external_id' => str_pad((string) $this->faker->unique()->numberBetween(42, 52), 4, '0', STR_PAD_LEFT),
            'vendor' => Vendor::Cp3000,
            'granularity' => Granularity::Line,
        ]);
    }
}
