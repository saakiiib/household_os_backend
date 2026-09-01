<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'first_name' => 'Admin',
            'last_name' => '',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('123456'),
            'is_admin' => true,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        User::create([
            'first_name' => 'Play',
            'last_name' => 'Reviewer',
            'email' => 'playreview@householdos.app',
            'password' => Hash::make('PlayReview@2026'),
            'is_admin' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public static function seedReviewer(): void
    {
        User::updateOrCreate(
            ['email' => 'playreview@householdos.app'],
            [
                'first_name' => 'Play',
                'last_name' => 'Reviewer',
                'password' => Hash::make('PlayReview@2026'),
                'is_admin' => false,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}
