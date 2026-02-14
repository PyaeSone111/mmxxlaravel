<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'admin@mmxx.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('123123'),
                'is_admin' => true,
            ]
        );
    }
}
