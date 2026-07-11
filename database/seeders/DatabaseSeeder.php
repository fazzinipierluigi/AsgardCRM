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
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'is_admin' => true, 'is_system' => true]
        );

        $user = User::factory()->create([
            'name' => 'Test User',
            'username' => 'test',
            'email' => 'test@example.com',
        ]);

        $user->assignRole($adminRole);

        $this->call(TranslationSeeder::class);
    }
}
