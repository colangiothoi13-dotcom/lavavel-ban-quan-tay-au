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
        $accounts = [
            [
                'email' => 'admin@example.com',
                'name' => 'Quản trị viên',
                'gender' => 'Nam',
                'birth_date' => '1990-01-01',
                'phone' => '0900000000',
                'password' => 'password',
                'role' => 'admin',
            ],
            [
                'email' => 'user@example.com',
                'name' => 'Người dùng mẫu',
                'gender' => 'Nữ',
                'birth_date' => '2000-05-15',
                'phone' => '0911111111',
                'password' => 'password',
                'role' => 'user',
            ],
            [
                'email' => 'demo@lavabeo.com',
                'name' => 'Demo User',
                'gender' => 'Nam',
                'birth_date' => '1998-02-10',
                'phone' => '0901234567',
                'password' => '12345678',
                'role' => 'user',
            ],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(['email' => $account['email']], [
                'name' => $account['name'],
                'gender' => $account['gender'],
                'birth_date' => $account['birth_date'],
                'phone' => $account['phone'],
                'password' => $account['password'],
                'role' => $account['role'],
                'email_verified_at' => now(),
            ]);
        }
    }
}
