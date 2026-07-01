<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Models\User;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Household $household;
    private HouseholdMember $adminMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->household = Household::create([
            'name'               => 'Security HH',
            'created_by_user_id' => $this->admin->id,
            'status'             => 'active',
        ]);
        $this->adminMember = HouseholdMember::create([
            'household_id' => $this->household->id,
            'user_id'      => $this->admin->id,
            'role'         => 'admin',
            'status'       => 'active',
            'joined_at'    => now(),
        ]);
    }

    // ── 1. Security Headers ──
    public function test_security_headers_are_present(): void
    {
        $response = $this->actingAs($this->admin, 'api')
                         ->getJson("/api/households/{$this->household->id}/members");

        $response->assertStatus(200);
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy');
    }

    // ── 2. Validation: UpdateMemberRoleRequest ──
    public function test_update_member_role_validation_fails_on_invalid_role(): void
    {
        $member = User::factory()->create(['status' => 'active']);
        $targetMember = HouseholdMember::create([
            'household_id' => $this->household->id,
            'user_id'      => $member->id,
            'role'         => 'member',
            'status'       => 'active',
            'joined_at'    => now(),
        ]);

        $response = $this->actingAs($this->admin, 'api')
                         ->patchJson("/api/households/{$this->household->id}/members/{$targetMember->id}", [
                             'role' => 'invalid_role_name',
                         ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    // ── 3. Validation: UpdateRenewalRequest ──
    public function test_update_renewal_validation_fails_on_invalid_data(): void
    {
        $renewal = Renewal::factory()->create([
            'household_id'        => $this->household->id,
            'responsible_user_id' => $this->admin->id,
            'created_by_user_id'  => $this->admin->id,
            'status'              => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'api')
                         ->patchJson("/api/renewals/{$renewal->id}", [
                             'category' => 'not-a-real-category',
                             'cost'     => -100, // Cost cannot be negative
                         ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category', 'cost']);
    }

    // ── 4. Rate Limiting ──
    public function test_uploads_rate_limiting(): void
    {
        RateLimiter::clear('uploads:' . $this->admin->id);
        RateLimiter::clear('uploads:' . '127.0.0.1');

        // Let's hit it 5 times (limit is 5/min)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($this->admin, 'api')
                             ->postJson("/api/households/{$this->household->id}/documents", [
                                 'title' => 'Doc ' . $i,
                             ]);
            // If it returns 422 or 201 or 403 or anything except 429, it's fine for this test
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th time should return 429
        $response = $this->actingAs($this->admin, 'api')
                         ->postJson("/api/households/{$this->household->id}/documents", [
                             'title' => 'Doc 6',
                         ]);

        $response->assertStatus(429);
    }

    public function test_downloads_rate_limiting(): void
    {
        RateLimiter::clear('downloads:' . $this->admin->id);
        
        // Mock a document
        $doc = Document::create([
            'household_id' => $this->household->id,
            'title' => 'Download test',
            'category' => 'other',
            'file_type' => 'pdf',
            'file_size_bytes' => 100,
            'uploaded_by_user_id' => $this->admin->id,
            'is_encrypted' => false,
            'checksum' => 'abc',
            'file_path' => 'docs/test.pdf',
            'file_name_original' => 'test.pdf',
            'file_name_stored' => 'stored.pdf',
            'mime_type' => 'application/pdf',
        ]);

        // Limit is 20/min
        for ($i = 0; $i < 20; $i++) {
            $response = $this->actingAs($this->admin, 'api')
                             ->getJson("/api/documents/{$doc->id}/download");
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        $response = $this->actingAs($this->admin, 'api')
                         ->getJson("/api/documents/{$doc->id}/download");
        $response->assertStatus(429);
    }

    public function test_renewals_rate_limiting(): void
    {
        RateLimiter::clear('renewals:' . $this->admin->id);

        // Limit is 30/min
        for ($i = 0; $i < 30; $i++) {
            $response = $this->actingAs($this->admin, 'api')
                             ->getJson("/api/households/{$this->household->id}/renewals");
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        $response = $this->actingAs($this->admin, 'api')
                         ->getJson("/api/households/{$this->household->id}/renewals");
        $response->assertStatus(429);
    }
}
