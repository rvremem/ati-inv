<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::connection('db_inv_ho')
            ->table('users')
            ->where('username', 'admin')
            ->exists();

        if (!$exists) {
            DB::connection('db_inv_ho')->table('users')->insert([
                'username'   => 'admin',
                'password'   => Hash::make('password123'),
                'name'       => 'Super Admin',
                'email'      => 'admin@atiph.net',
                'role'       => 'SA',
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('Super admin created: admin / password123');
        } else {
            $this->command->warn('Super admin already exists, skipping.');
        }
    }
}
