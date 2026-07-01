<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Generate Passport keys
        $this->artisan('passport:keys', ['--force' => true]);
        
        // Manually seed the personal access client so Passport can find it
        \DB::table('oauth_clients')->insert([
            'id' => '90000000-0000-0000-0000-000000000000',
            'name' => 'Personal Access Client',
            'secret' => 'xyz',
            'provider' => 'users',
            'redirect_uris' => json_encode(['http://localhost']),
            'grant_types' => json_encode(['personal_access']),
            'revoked' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Registration ─────────────────────────────────────────────────────────

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email'      => 'jane@example.com',
            'password'   => 'Password123!',
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['user', 'token', 'token_type'],
                 ])
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'email'      => 'dupe@example.com',
            'password'   => 'Password123!',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email'      => 'weak@example.com',
            'password'   => '1234',
            'first_name' => 'Weak',
            'last_name'  => 'Pass',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    public function test_register_creates_household_when_name_provided(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email'          => 'admin@example.com',
            'password'       => 'Password123!',
            'first_name'     => 'Admin',
            'last_name'      => 'User',
            'household_name' => 'The Smith Family',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotNull($data['household']);
        $this->assertEquals('The Smith Family', $data['household']['name']);
        $this->assertDatabaseHas('households', ['name' => 'The Smith Family']);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_user_can_login_successfully(): void
    {
        $user = User::factory()->create([
            'email'    => 'login@example.com',
            'password' => bcrypt('Password123!'),
            'status'   => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['token']])
                 ->assertJson(['success' => true]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'  => 'user@example.com',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success' => false]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email'    => 'inactive@example.com',
            'password' => bcrypt('Password123!'),
            'status'   => 'inactive',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403);
    }

    // ── Auth Endpoints ────────────────────────────────────────────────────────

    public function test_get_user_requires_auth(): void
    {
        $this->getJson('/api/auth/user')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'api')
                         ->getJson('/api/auth/user');

        $response->assertStatus(200)
                 ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/auth/logout');

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }
}
