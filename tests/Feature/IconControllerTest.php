<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get('/tabler-icons/outline/search')->assertRedirect(route('login'));
});

test('a known icon is served as inline svg with a cacheable content type', function () {
    $response = $this->actingAs(User::factory()->create())->get('/tabler-icons/outline/search');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/svg+xml');
    $response->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    expect($response->getContent())->toContain('<svg');
});

test('an unknown icon 404s', function () {
    $this->actingAs(User::factory()->create())
        ->get('/tabler-icons/outline/not-a-real-icon')
        ->assertNotFound();
});

test('a name outside the allowed pattern 404s at the routing level', function () {
    $this->actingAs(User::factory()->create())
        ->get('/tabler-icons/outline/..%2F..%2Fetc%2Fpasswd')
        ->assertNotFound();
});
