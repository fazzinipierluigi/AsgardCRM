<?php

use Fazzinipierluigi\CrmCore\Models\WorkflowApiEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.api-endpoints.index'))->assertRedirect(route('login'));
});

test('admin can view the create form', function () {
    $this->actingAs(adminUser())->get(route('admin.api-endpoints.create'))->assertOk();
});

test('admin can view the edit form', function () {
    $endpoint = WorkflowApiEndpoint::create(['name' => 'Da modificare', 'base_url' => 'https://api.example.com', 'config' => ['auth_type' => 'none']]);

    $this->actingAs(adminUser())->get(route('admin.api-endpoints.edit', $endpoint))->assertOk();
});

test('admin can create a global bearer-auth endpoint with an encrypted token', function () {
    $response = $this->actingAs(adminUser())->post(route('admin.api-endpoints.store'), [
        'name' => 'CRM esterno',
        'workflow_id' => '',
        'base_url' => 'https://api.example.com',
        'auth_type' => 'bearer',
        'token' => 'segreto123',
    ]);

    $response->assertRedirect(route('admin.api-endpoints.index'));

    $endpoint = WorkflowApiEndpoint::firstOrFail();
    expect($endpoint->workflow_id)->toBeNull();
    expect($endpoint->config['token'])->toBe('segreto123');

    $raw = DB::table('workflow_api_endpoints')->first();
    expect($raw->config)->not->toContain('segreto123');
});

test('a bearer endpoint without a token is rejected', function () {
    $this->actingAs(adminUser())->post(route('admin.api-endpoints.store'), [
        'name' => 'CRM esterno',
        'workflow_id' => '',
        'base_url' => 'https://api.example.com',
        'auth_type' => 'bearer',
    ])->assertSessionHasErrors('token');
});

test('a base_url without http(s) scheme is rejected', function () {
    $this->actingAs(adminUser())->post(route('admin.api-endpoints.store'), [
        'name' => 'CRM esterno',
        'workflow_id' => '',
        'base_url' => 'ftp://api.example.com',
        'auth_type' => 'none',
    ])->assertSessionHasErrors('base_url');
});

test('leaving the token blank on update keeps the previous one', function () {
    $endpoint = WorkflowApiEndpoint::create([
        'name' => 'Originale',
        'base_url' => 'https://api.example.com',
        'config' => ['auth_type' => 'bearer', 'token' => 'vecchio-token'],
    ]);

    $this->actingAs(adminUser())->put(route('admin.api-endpoints.update', $endpoint), [
        'name' => 'Rinominato',
        'workflow_id' => '',
        'base_url' => 'https://api.example.com',
        'auth_type' => 'bearer',
        'token' => '',
    ]);

    expect($endpoint->fresh()->config['token'])->toBe('vecchio-token');
    expect($endpoint->fresh()->name)->toBe('Rinominato');
});

test('admin can delete an endpoint', function () {
    $endpoint = WorkflowApiEndpoint::create(['name' => 'Da eliminare', 'base_url' => 'https://api.example.com', 'config' => ['auth_type' => 'none']]);

    $this->actingAs(adminUser())->delete(route('admin.api-endpoints.destroy', $endpoint))
        ->assertRedirect(route('admin.api-endpoints.index'));

    expect(WorkflowApiEndpoint::find($endpoint->id))->toBeNull();
});
