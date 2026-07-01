<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->household = Household::create([
            'name'               => 'Test HH',
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

    public function test_member_can_create_task(): void
    {
        $response = $this->actingAs($this->admin, 'api')
                         ->postJson("/api/households/{$this->household->id}/tasks", [
                             'title'     => 'Clean kitchen',
                             'task_type' => 'one-time',
                             'due_date'  => now()->addDays(3)->toDateString(),
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.title', 'Clean kitchen');

        $this->assertDatabaseHas('tasks', ['title' => 'Clean kitchen']);
    }

    public function test_task_creation_fails_without_title(): void
    {
        $this->actingAs($this->admin, 'api')
             ->postJson("/api/households/{$this->household->id}/tasks", [
                 'task_type' => 'one-time',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['title']);
    }

    public function test_task_creation_fails_with_invalid_task_type(): void
    {
        $this->actingAs($this->admin, 'api')
             ->postJson("/api/households/{$this->household->id}/tasks", [
                 'title'     => 'Bad Task',
                 'task_type' => 'invalid_type',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['task_type']);
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_member_can_list_tasks(): void
    {
        Task::factory()->count(3)->create([
            'household_id'      => $this->household->id,
            'created_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'api')
                         ->getJson("/api/households/{$this->household->id}/tasks");

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_outsider_cannot_list_tasks(): void
    {
        $outsider = User::factory()->create(['status' => 'active']);

        $this->actingAs($outsider, 'api')
             ->getJson("/api/households/{$this->household->id}/tasks")
             ->assertStatus(403);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_member_can_view_task(): void
    {
        $task = Task::factory()->create([
            'household_id'       => $this->household->id,
            'created_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'api')
                         ->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $task->id);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_member_can_update_task(): void
    {
        $task = Task::factory()->create([
            'household_id'       => $this->household->id,
            'created_by_user_id' => $this->admin->id,
            'title'              => 'Old Title',
        ]);

        $response = $this->actingAs($this->admin, 'api')
                         ->patchJson("/api/tasks/{$task->id}", [
                             'title' => 'Updated Title',
                         ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Updated Title']);
    }

    // ── Complete ──────────────────────────────────────────────────────────────

    public function test_member_can_complete_task(): void
    {
        $task = Task::factory()->create([
            'household_id'       => $this->household->id,
            'created_by_user_id' => $this->admin->id,
            'status'             => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'api')
                         ->postJson("/api/tasks/{$task->id}/complete");

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_member_can_delete_task(): void
    {
        $task = Task::factory()->create([
            'household_id'       => $this->household->id,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'api')
             ->deleteJson("/api/tasks/{$task->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
