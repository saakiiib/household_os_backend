<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id'       => Household::factory(),
            'created_by_user_id' => User::factory(),
            'assigned_to_user_id'=> null,
            'title'              => $this->faker->sentence(3),
            'description'        => $this->faker->optional()->paragraph(),
            'task_type'          => $this->faker->randomElement(['one-time', 'recurring', 'rotating']),
            'status'             => 'pending',
            'priority'           => $this->faker->randomElement(['low', 'medium', 'high']),
            'due_date'           => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'reward_points'      => $this->faker->numberBetween(0, 100),
        ];
    }
}
