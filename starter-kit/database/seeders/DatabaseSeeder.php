<?php

namespace Database\Seeders;

use App\Models\User;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database: one admin user, ready to log in
     * and start building the first entity from the UI — no demo data,
     * that's the AsgardCRM app's own territory (ClientiEntitySeeder
     * and friends), not the starter kit's.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'is_admin' => true, 'is_system' => true]
        );

        $user = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => 'password',
            ]
        );

        $user->assignRole($role);
    }
}
