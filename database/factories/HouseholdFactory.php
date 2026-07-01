<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'               => $this->faker->company() . ' Household',
            'created_by_user_id' => User::factory(),
            'status'             => 'active',
        ];
    }
}
