<?php

use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.translations.index'))->assertRedirect(route('login'));
});

test('users without privileges are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.translations.index'))->assertForbidden();
});

test('admin can view the translations index', function () {
    $this->actingAs(adminUser())->get(route('admin.translations.index'))->assertOk();
});

test('admin can create a translation key with values for multiple languages', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.translations.store'), [
        'key' => 'dashboard.welcome',
        'values' => ['it' => 'Benvenuto', 'en' => 'Welcome'],
    ]);

    $response->assertRedirect(route('admin.translations.index'));
    expect(Translation::where('key', 'dashboard.welcome')->where('language', 'it')->value('value'))->toBe('Benvenuto');
    expect(Translation::where('key', 'dashboard.welcome')->where('language', 'en')->value('value'))->toBe('Welcome');
});

test('creating a translation skips languages left blank', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.translations.store'), [
        'key' => 'dashboard.welcome',
        'values' => ['it' => 'Benvenuto', 'en' => ''],
    ]);

    expect(Translation::where('key', 'dashboard.welcome')->count())->toBe(1);
});

test('creating a translation requires at least one language value', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.translations.store'), [
        'key' => 'dashboard.welcome',
        'values' => ['it' => '', 'en' => ''],
    ]);

    $response->assertSessionHasErrors('values');
});

test('the key must not already exist', function () {
    $admin = adminUser();
    Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);

    $response = $this->actingAs($admin)->post(route('admin.translations.store'), [
        'key' => 'dashboard.welcome',
        'values' => ['en' => 'Welcome'],
    ]);

    $response->assertSessionHasErrors('key');
});

test('admin can view the edit form with existing values prefilled', function () {
    seedLanguages();
    $admin = adminUser();
    $translation = Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);

    $this->actingAs($admin)->get(route('admin.translations.edit', $translation))
        ->assertOk()
        ->assertSee('Benvenuto');
});

test('admin can update, add and clear language values for a key', function () {
    $admin = adminUser();
    $translation = Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);
    Translation::create(['key' => 'dashboard.welcome', 'language' => 'en', 'value' => 'Welcome']);

    $response = $this->actingAs($admin)->put(route('admin.translations.update', $translation), [
        'values' => ['it' => 'Ciao', 'en' => ''],
    ]);

    $response->assertRedirect(route('admin.translations.index'));
    expect(Translation::where('key', 'dashboard.welcome')->where('language', 'it')->value('value'))->toBe('Ciao');
    expect(Translation::where('key', 'dashboard.welcome')->where('language', 'en')->exists())->toBeFalse();
});

test('admin can delete a translation key across all languages', function () {
    $admin = adminUser();
    $translation = Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);
    Translation::create(['key' => 'dashboard.welcome', 'language' => 'en', 'value' => 'Welcome']);

    $this->actingAs($admin)->delete(route('admin.translations.destroy', $translation));

    expect(Translation::where('key', 'dashboard.welcome')->count())->toBe(0);
});

test('translations datatable endpoint returns one pivoted row per key', function () {
    seedLanguages();
    $admin = adminUser();
    Translation::create(['key' => 'findable.key', 'language' => 'it', 'value' => 'Trovabile']);
    Translation::create(['key' => 'findable.key', 'language' => 'en', 'value' => 'Findable']);

    $response = $this->actingAs($admin)->getJson(route('admin.translations.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    $row = collect($response->json('data'))->firstWhere('key', 'findable.key');
    expect($row['it'])->toBe('Trovabile');
    expect($row['en'])->toBe('Findable');
});

test('translations datatable endpoint supports search', function () {
    seedLanguages();
    $admin = adminUser();
    Translation::create(['key' => 'findable.key', 'language' => 'it', 'value' => 'Trovabile']);
    Translation::create(['key' => 'other.key', 'language' => 'it', 'value' => 'Altro']);

    $response = $this->actingAs($admin)->getJson(route('admin.translations.data', ['start' => 0, 'limit' => 25, 'globalSearch' => 'findable']));

    $keys = collect($response->json('data'))->pluck('key');
    expect($keys)->toContain('findable.key');
    expect($keys)->not->toContain('other.key');
});
