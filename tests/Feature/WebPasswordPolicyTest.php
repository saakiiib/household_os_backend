<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Password policy must be min 8 everywhere (app register, app reset,
 * app change-password, web register, web change-password).
 */
class WebPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_register_rejects_short_password(): void
    {
        Notification::fake();

        $response = $this->post(route('web.register.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'shortpw.user@gmail.com',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'shortpw.user@gmail.com']);
    }

    public function test_web_password_change_rejects_short_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.password'), [
            'current_password' => 'password',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_web_password_change_accepts_long_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.password'), [
            'current_password' => 'password',
            'password' => 'newsecret1',
            'password_confirmation' => 'newsecret1',
        ]);

        $response->assertSessionHas('status', 'Password changed.');
        $this->assertTrue(Hash::check('newsecret1', $user->fresh()->password));
    }
}
