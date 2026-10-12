<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Proves the website payment flow always uses one explicit household:
 * register creates no household, the household step provisions exactly
 * one household with one trial, and plans/subscribe/success/account all
 * agree on that household.
 */
class WebHouseholdFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
    }

    public function test_register_creates_user_without_household(): void
    {
        Notification::fake();

        $response = $this->post(route('web.register.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe.household.test@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::where('email', 'jane.doe.household.test@gmail.com')->firstOrFail();
        $this->assertSame(0, HouseholdMember::where('user_id', $user->id)->count());
        $this->assertSame(0, Subscription::where('user_id', $user->id)->count());
    }

    public function test_plans_redirects_to_household_create_when_no_household(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('billing.plans'))
            ->assertRedirect(route('household.create'));
    }

    public function test_household_create_provisions_trial_and_shows_on_plans(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('household.store'), ['household_name' => 'The Doe Family'])
            ->assertRedirect(route('billing.plans'));

        $household = Household::where('created_by_user_id', $user->id)->firstOrFail();
        $this->assertSame('The Doe Family', $household->name);
        $this->assertDatabaseHas('household_members', [
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $trial = Subscription::where('household_id', $household->id)->firstOrFail();
        $this->assertSame('trial', $trial->status);
        $this->assertSame('complete', $trial->plan->code);
        $this->assertNotNull($trial->trial_ends_at);

        // The plans page must show THIS household (the old bug showed a
        // stranger's household here).
        $this->actingAs($user)
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertSee('The Doe Family');
    }

    public function test_second_household_create_is_blocked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('household.store'), ['household_name' => 'First']);

        $this->actingAs($user)
            ->post(route('household.store'), ['household_name' => 'Second'])
            ->assertRedirect(route('billing.plans'));

        $this->assertSame(1, Household::where('created_by_user_id', $user->id)->count());
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }

    public function test_foreign_pinned_household_is_ignored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('household.store'), ['household_name' => 'My Home']);

        // A pinned id the user is not a member of must fall back to the
        // user's own household, never display the stranger's one.
        $stranger = Household::create(['name' => 'Bhuyain', 'created_by_user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user)
            ->withSession(['web_household_id' => $stranger->id])
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertSee('My Home')
            ->assertDontSee('Bhuyain');
    }

    public function test_subscribe_without_household_redirects_to_create(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('code', 'tasks')->firstOrFail();

        // Must redirect before any Stripe call happens.
        $this->actingAs($user)
            ->post(route('billing.subscribe'), ['plan_id' => $plan->id, 'billing_period' => 'monthly'])
            ->assertRedirect(route('household.create'));

        $this->assertSame(0, Subscription::count());
    }

    public function test_subscribe_blocked_on_household_mismatch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('household.store'), ['household_name' => 'Real Home']);
        $real = Household::where('created_by_user_id', $user->id)->firstOrFail();

        // Second active membership created outside the guards.
        $other = Household::create(['name' => 'Bhuyain', 'created_by_user_id' => $user->id, 'status' => 'active']);
        HouseholdMember::create([
            'household_id' => $other->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // InnoDB returns the oldest membership row first, so the checkout
        // path resolves $real while the session pins $other: ambiguous.
        $this->assertSame($real->id, $user->activeHousehold()->id);

        $plan = SubscriptionPlan::where('code', 'tasks')->firstOrFail();
        $response = $this->actingAs($user)
            ->withSession(['web_household_id' => $other->id])
            ->post(route('billing.subscribe'), ['plan_id' => $plan->id, 'billing_period' => 'monthly']);

        $response->assertRedirect();
        $response->assertSessionHasErrors('plan');

        // Nothing was charged or created.
        $this->assertSame(1, Subscription::where('household_id', $real->id)->count());
        $this->assertSame(0, Subscription::where('household_id', $other->id)->count());
    }

    public function test_account_page_shows_pinned_household(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('household.store'), ['household_name' => 'My Home']);

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee('My Home');
    }
}
