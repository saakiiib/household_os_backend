<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdMemberTest extends TestCase
{
    use RefreshDatabase;

    private function createHousehold(User $admin): Household
    {
        $household = Household::create([
            'name'               => 'Test Household',
            'created_by_user_id' => $admin->id,
            'status'             => 'active',
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id'      => $admin->id,
            'role'         => 'admin',
            'status'       => 'active',
            'joined_at'    => now(),
        ]);
        return $household;
    }

    // ── List Members ──────────────────────────────────────────────────────────

    public function test_admin_can_list_members(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $household = $this->createHousehold($admin);

        $response = $this->actingAs($admin, 'api')
                         ->getJson("/api/households/{$household->id}/members");

        $response->assertStatus(200)
                 ->assertJsonStructure(['success', 'data']);
    }

    public function test_unauthenticated_user_cannot_list_members(): void
    {
        $admin = User::factory()->create();
        $household = $this->createHousehold($admin);

        $this->getJson("/api/households/{$household->id}/members")
             ->assertStatus(401);
    }

    public function test_non_member_cannot_list_members(): void
    {
        $admin     = User::factory()->create(['status' => 'active']);
        $outsider  = User::factory()->create(['status' => 'active']);
        $household = $this->createHousehold($admin);

        $this->actingAs($outsider, 'api')
             ->getJson("/api/households/{$household->id}/members")
             ->assertStatus(403);
    }

    // ── Invite ────────────────────────────────────────────────────────────────

    public function test_admin_can_invite_member(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $invitee = User::factory()->create(['status' => 'active']);
        $household = $this->createHousehold($admin);

        $response = $this->actingAs($admin, 'api')
                         ->postJson("/api/households/{$household->id}/invitations", [
                             'email' => $invitee->email,
                             'role'  => 'member',
                         ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);
    }

    public function test_regular_member_cannot_invite(): void
    {
        $admin   = User::factory()->create(['status' => 'active']);
        $member  = User::factory()->create(['status' => 'active']);
        $household = $this->createHousehold($admin);

        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id'      => $member->id,
            'role'         => 'member',
            'status'       => 'active',
            'joined_at'    => now(),
        ]);

        $this->actingAs($member, 'api')
             ->postJson("/api/households/{$household->id}/invitations", [
                 'email' => 'anyone@example.com',
                 'role'  => 'member',
             ])
             ->assertStatus(403);
    }

    // ── Role Update ───────────────────────────────────────────────────────────

    public function test_admin_can_update_member_role(): void
    {
        $admin  = User::factory()->create(['status' => 'active']);
        $member = User::factory()->create(['status' => 'active']);
        $household = $this->createHousehold($admin);

        $hm = HouseholdMember::create([
            'household_id' => $household->id,
            'user_id'      => $member->id,
            'role'         => 'member',
            'status'       => 'active',
            'joined_at'    => now(),
        ]);

        $response = $this->actingAs($admin, 'api')
                         ->patchJson("/api/households/{$household->id}/members/{$hm->id}", [
                             'role' => 'co-admin',
                         ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('household_members', [
            'id'   => $hm->id,
            'role' => 'co-admin',
        ]);
    }

    // ── Remove Member ─────────────────────────────────────────────────────────

    public function test_admin_can_remove_member(): void
    {
        $admin  = User::factory()->create(['status' => 'active']);
        $member = User::factory()->create(['status' => 'active']);
        $household = $this->createHousehold($admin);

        $hm = HouseholdMember::create([
            'household_id' => $household->id,
            'user_id'      => $member->id,
            'role'         => 'member',
            'status'       => 'active',
            'joined_at'    => now(),
        ]);

        $response = $this->actingAs($admin, 'api')
                         ->deleteJson("/api/households/{$household->id}/members/{$hm->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('household_members', [
            'id'     => $hm->id,
            'status' => 'active',
        ]);
    }
}
