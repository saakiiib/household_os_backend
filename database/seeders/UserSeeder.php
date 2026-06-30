<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    User::create([
      'name' => 'Admin',
      'email' => 'admin@gmail.com',
      'password' => Hash::make('123456'),
      'user_type' => 1,
      'status' => 1,
    ]);

    // User::create([
    //   'name' => 'User',
    //   'email' => 'user@gmail.com',
    //   'password' => Hash::make('123456'),
    //   'user_type' => 0,
    //   'status' => 1,
    // ]);
  }
}
