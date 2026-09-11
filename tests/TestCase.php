<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Single-operator session (PRD §3): every dashboard API test acts as
     * the one operator.
     */
    protected function actingAsUser(): User
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        return $user;
    }
}
