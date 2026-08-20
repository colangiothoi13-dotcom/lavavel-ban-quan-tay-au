<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Quản trị viên', 'gender' => 'Nam', 'birth_date' => '1990-01-01',
            'phone' => '0900000000', 'password' => 'password', 'role' => 'admin',
        ]);

        User::updateOrCreate(['email' => 'user@example.com'], [
            'name' => 'Người dùng mẫu', 'gender' => 'Nữ', 'birth_date' => '2000-05-15',
            'phone' => '0911111111', 'password' => 'password', 'role' => 'user',
        ]);
    }
}
