<?php

use App\Models\Language;
use App\Models\Translation;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DuskTestCase;
use Tests\TestCase;

pest()->extend(DuskTestCase::class)
    ->use(DatabaseMigrations::class)
    ->afterEach(fn () => Translation::forgetCache())
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->afterEach(fn () => Translation::forgetCache())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a user with the (system) admin role, which bypasses every
 * Just A Gate permission check.
 */
function adminUser(): User
{
    $role = Role::firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Administrator', 'is_admin' => true, 'is_system' => true]
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Seed the "it"/"en" languages (normally done by LanguageSeeder during
 * install) — needed by any test that touches translations/preferences,
 * since RefreshDatabase doesn't run seeders automatically.
 */
function seedLanguages(): void
{
    Language::firstOrCreate(['code' => 'it'], ['name' => 'Italiano']);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English']);
}

/**
 * Creates a Workflow with an empty, published WorkflowVersion #1 as
 * its current_version_id — the state every real workflow is in after
 * its first builder save. Tests build the version's nodes/edges via
 * `WorkflowNode::factory()->for($workflow->currentVersion)`.
 *
 * @param  array<string, mixed>  $attributes
 */
function wfWorkflowWithVersion(array $attributes = []): Workflow
{
    $workflow = Workflow::factory()->create($attributes);
    $version = WorkflowVersion::factory()->for($workflow)->create(['version' => 1]);
    $workflow->update(['current_version_id' => $version->id]);

    return $workflow->fresh();
}
