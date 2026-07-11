<?php

use App\Models\Language;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.languages.index'))->assertRedirect(route('login'));
});

test('users without privileges are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.languages.index'))->assertForbidden();
});

test('admin can view the languages index', function () {
    $this->actingAs(adminUser())->get(route('admin.languages.index'))->assertOk();
});

test('admin can add a new language', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.languages.store'), [
        'code' => 'fr',
        'name' => 'Français',
    ]);

    $response->assertRedirect(route('admin.languages.index'));
    expect(Language::where('code', 'fr')->exists())->toBeTrue();
});

test('language code must be unique', function () {
    $admin = adminUser();
    Language::create(['code' => 'fr', 'name' => 'Français']);

    $response = $this->actingAs($admin)->post(route('admin.languages.store'), [
        'code' => 'fr',
        'name' => 'French (duplicate)',
    ]);

    $response->assertSessionHasErrors('code');
});

test('admin can delete an unused language', function () {
    $admin = adminUser();
    $language = Language::create(['code' => 'fr', 'name' => 'Français']);

    $this->actingAs($admin)->delete(route('admin.languages.destroy', $language));

    expect(Language::find($language->id))->toBeNull();
});

test('a language with existing translations cannot be deleted', function () {
    $admin = adminUser();
    $language = Language::create(['code' => 'fr', 'name' => 'Français']);
    Translation::create(['key' => 'dashboard.welcome', 'language' => 'fr', 'value' => 'Bienvenue']);

    $response = $this->actingAs($admin)->delete(route('admin.languages.destroy', $language));

    $response->assertRedirect();
    expect(Language::find($language->id))->not->toBeNull();
});
