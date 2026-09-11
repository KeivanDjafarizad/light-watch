<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_valid_data()
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->post(route('register.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }
}
