<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('data.email', 'demo@example.com');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'demo@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'demo@example.com',
            'password' => 'not-the-password',
        ]);

        $response->assertStatus(422)->assertJsonPath('errors.email.0', 'Неверный email или пароль.');
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_guests_cannot_read_the_current_user(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_read_their_profile(): void
    {
        $user = User::factory()->create(['name' => 'Демо-пользователь']);

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.name', 'Демо-пользователь');
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/logout')->assertOk();

        // Именно 'web': middleware auth:sanctum по ходу запроса делает
        // sanctum гардом по умолчанию, а тот кеширует пользователя в памяти
        // процесса. В бою каждый запрос отдельный, здесь же процесс общий,
        // поэтому смотрим сессионный гард, из которого и выходили.
        $this->assertGuest('web');
    }
}
