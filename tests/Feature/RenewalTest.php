<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->household = Household::create([
            'name'               => 'Renewal HH',
            'created_by_user_id' => $this->admin->id,
            'status'             => 'active',
        ]);
        HouseholdMember::create([
            'household_id' => $this->household->id,
            'user_id'      => $this->admin->id,
            'role'         => 'admin',
            'status'       => 'active',
            'joined_at'    => now(),
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_member_can_create_renewal(): void
    {
        $response = $this->actingAs($this->admin, 'api')
                         ->postJson("/api/households/{$this->household->id}/renewals", [
                             'title'               => 'Car Insurance',
                             'category'            => 'insurance',
                             'renewal_date'        => now()->addYear()->toDateString(),
                             'responsible_user_id' => $this->admin->id,
                             'frequency'           => 'annual',
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.title', 'Car Insurance');

        $this->assertDatabaseHas('renewals', ['title' => 'Car Insurance']);
    }

    public function test_renewal_creation_fails_without_required_fields(): void
    {
        $this->actingAs($this->admin, 'api')
             ->postJson("/api/households/{$this->household->id}/renewals", [
                 'title' => 'Incomplete',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['category', 'renewal_date', 'responsible_user_id', 'frequency']);
    }

    public function test_renewal_creation_fails_with_invalid_category(): void
    {
        $this->actingAs($this->admin, 'api')
             ->postJson("/api/households/{$this->household->id}/renewals", [
                 'title'               => 'Test',
                 'category'            => 'not_a_real_category',
                 'renewal_date'        => now()->addYear()->toDateString(),
                 'responsible_user_id' => $this->admin->id,
                 'frequency'           => 'annual',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['category']);
    }

    // ── List & Upcoming ───────────────────────────────────────────────────────

    public function test_member_can_list_renewals(): void
    {
        Renewal::factory()->count(2)->create([
            'household_id'        => $this->household->id,
            'responsible_user_id' => $this->admin->id,
            'created_by_user_id'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'api')
             ->getJson("/api/households/{$this->household->id}/renewals")
             ->assertStatus(200)
             ->assertJsonCount(2, 'data');
    }

    public function test_upcoming_renewals_endpoint_returns_data(): void
    {
        Renewal::factory()->create([
            'household_id'        => $this->household->id,
            'responsible_user_id' => $this->admin->id,
            'created_by_user_id'  => $this->admin->id,
            'renewal_date'        => now()->addDays(20)->toDateString(),
            'status'              => 'active',
        ]);

        $this->actingAs($this->admin, 'api')
             ->getJson("/api/households/{$this->household->id}/renewals/upcoming")
             ->assertStatus(200);
    }

    // ── Complete ──────────────────────────────────────────────────────────────

    public function test_member_can_complete_renewal(): void
    {
        $renewal = Renewal::factory()->create([
            'household_id'        => $this->household->id,
            'responsible_user_id' => $this->admin->id,
            'created_by_user_id'  => $this->admin->id,
            'status'              => 'active',
        ]);

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/renewals/{$renewal->id}/complete")
             ->assertStatus(200);

        $this->assertDatabaseHas('renewals', [
            'id'     => $renewal->id,
            'status' => 'renewed',
        ]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_member_can_delete_renewal(): void
    {
        $renewal = Renewal::factory()->create([
            'household_id'        => $this->household->id,
            'responsible_user_id' => $this->admin->id,
            'created_by_user_id'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'api')
             ->deleteJson("/api/renewals/{$renewal->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('renewals', ['id' => $renewal->id]);
    }
}
