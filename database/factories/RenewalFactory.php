<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RenewalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id'        => Household::factory(),
            'created_by_user_id'  => User::factory(),
            'responsible_user_id' => User::factory(),
            'title'               => $this->faker->words(3, true),
            'category'            => $this->faker->randomElement(['insurance', 'passport', 'subscription', 'warranty', 'contract', 'medical', 'other']),
            'renewal_date'        => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'cost'                => $this->faker->randomFloat(2, 10, 1000),
            'currency'            => 'USD',
            'frequency'           => $this->faker->randomElement(['annual', 'bi-annual', 'quarterly', 'monthly', 'one-time']),
            'status'              => 'active',
            'notes'               => $this->faker->sentence(),
        ];
    }
}
