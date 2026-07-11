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

test('admin can create a translation', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.translations.store'), [
        'key' => 'dashboard.welcome',
        'language' => 'it',
        'value' => 'Benvenuto',
    ]);

    $response->assertRedirect(route('admin.translations.index'));
    expect(Translation::where('key', 'dashboard.welcome')->where('language', 'it')->exists())->toBeTrue();
});

test('the same key can exist in more than one language', function () {
    $admin = adminUser();
    Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);

    $response = $this->actingAs($admin)->post(route('admin.translations.store'), [
        'key' => 'dashboard.welcome',
        'language' => 'en',
        'value' => 'Welcome',
    ]);

    $response->assertRedirect(route('admin.translations.index'));
    expect(Translation::where('key', 'dashboard.welcome')->count())->toBe(2);
});

test('the same key and language pair must be unique', function () {
    $admin = adminUser();
    Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);

    $response = $this->actingAs($admin)->post(route('admin.translations.store'), [
        'key' => 'dashboard.welcome',
        'language' => 'it',
        'value' => 'Duplicato',
    ]);

    $response->assertSessionHasErrors('key');
});

test('language must be a supported option', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.translations.store'), [
        'key' => 'dashboard.welcome',
        'language' => 'fr',
        'value' => 'Bienvenue',
    ]);

    $response->assertSessionHasErrors('language');
});

test('admin can update a translation', function () {
    $admin = adminUser();
    $translation = Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);

    $response = $this->actingAs($admin)->put(route('admin.translations.update', $translation), [
        'key' => 'dashboard.welcome',
        'language' => 'it',
        'value' => 'Ciao',
    ]);

    $response->assertRedirect(route('admin.translations.index'));
    expect($translation->fresh()->value)->toBe('Ciao');
});

test('admin can delete a translation', function () {
    $admin = adminUser();
    $translation = Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);

    $this->actingAs($admin)->delete(route('admin.translations.destroy', $translation));

    expect(Translation::find($translation->id))->toBeNull();
});

test('translations datatable endpoint returns json data', function () {
    $admin = adminUser();
    Translation::create(['key' => 'findable.key', 'language' => 'it', 'value' => 'Trovabile']);

    $response = $this->actingAs($admin)->getJson(route('admin.translations.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('key'))->toContain('findable.key');
});
