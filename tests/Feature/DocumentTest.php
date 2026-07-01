<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->household = Household::create([
            'name'               => 'Doc HH',
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

    // ── Upload ────────────────────────────────────────────────────────────────

    public function test_member_can_upload_document(): void
    {
        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin, 'api')
                         ->postJson("/api/households/{$this->household->id}/documents", [
                             'file'     => $file,
                             'title'    => 'My Passport',
                             'category' => 'passport',
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.title', 'My Passport');

        $this->assertDatabaseHas('documents', ['title' => 'My Passport']);
    }

    public function test_upload_fails_without_file(): void
    {
        $this->actingAs($this->admin, 'api')
             ->postJson("/api/households/{$this->household->id}/documents", [
                 'title'    => 'No file',
                 'category' => 'passport',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_fails_with_invalid_category(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/households/{$this->household->id}/documents", [
                 'file'     => $file,
                 'title'    => 'Bad cat',
                 'category' => 'not_a_category',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['category']);
    }

    public function test_upload_fails_with_oversized_file(): void
    {
        // 11 MB — above the 10 MB limit
        $file = UploadedFile::fake()->create('huge.pdf', 11264, 'application/pdf');

        $this->actingAs($this->admin, 'api')
             ->postJson("/api/households/{$this->household->id}/documents", [
                 'file'     => $file,
                 'title'    => 'Too big',
                 'category' => 'passport',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['file']);
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_member_can_list_documents(): void
    {
        // Create via upload so encryption happens correctly
        $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');
        $this->actingAs($this->admin, 'api')
             ->postJson("/api/households/{$this->household->id}/documents", [
                 'file'     => $file,
                 'title'    => 'Doc 1',
                 'category' => 'insurance',
             ]);

        $this->actingAs($this->admin, 'api')
             ->getJson("/api/households/{$this->household->id}/documents")
             ->assertStatus(200)
             ->assertJsonStructure(['success', 'data']);
    }

    // ── Download ──────────────────────────────────────────────────────────────

    public function test_member_can_download_document(): void
    {
        $file = UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf');

        $createResp = $this->actingAs($this->admin, 'api')
                           ->postJson("/api/households/{$this->household->id}/documents", [
                               'file'     => $file,
                               'title'    => 'Contract',
                               'category' => 'contract',
                           ]);

        $docId = $createResp->json('data.id');

        $this->actingAs($this->admin, 'api')
             ->getJson("/api/documents/{$docId}/download")
             ->assertStatus(200);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_member_can_delete_document(): void
    {
        $file = UploadedFile::fake()->create('delete_me.pdf', 50, 'application/pdf');

        $createResp = $this->actingAs($this->admin, 'api')
                           ->postJson("/api/households/{$this->household->id}/documents", [
                               'file'     => $file,
                               'title'    => 'Delete Me',
                               'category' => 'other',
                           ]);

        $docId = $createResp->json('data.id');

        $this->actingAs($this->admin, 'api')
             ->deleteJson("/api/documents/{$docId}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('documents', ['id' => $docId]);
    }
}
