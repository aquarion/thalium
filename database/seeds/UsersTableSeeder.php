<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // TODO: replace hardcoded password with an env var (APP_SEED_PASSWORD)
        DB::table('users')->insertOrIgnore([
            'name' => 'Aquarion',
            'email' => 'nicholas@aquarionics.com',
            'password' => Hash::make('toaster123'),
        ]);
    }
}
