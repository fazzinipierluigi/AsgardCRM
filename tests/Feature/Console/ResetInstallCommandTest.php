<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

test('wipes the database and removes the installed marker', function () {
    file_put_contents(storage_path('installed'), 'x');
    User::factory()->create();

    $this->artisan('app:reset-install', ['--force' => true])->assertSuccessful();

    expect(file_exists(storage_path('installed')))->toBeFalse()
        ->and(DB::table('users')->count())->toBe(0);
});
