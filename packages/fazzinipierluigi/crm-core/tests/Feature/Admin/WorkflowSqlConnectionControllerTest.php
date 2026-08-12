<?php

use Fazzinipierluigi\CrmCore\Models\WorkflowSqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.sql-connections.index'))->assertRedirect(route('login'));
});

test('admin can view the create form', function () {
    $this->actingAs(adminUser())->get(route('admin.sql-connections.create'))->assertOk();
});

test('admin can view the edit form', function () {
    $connection = WorkflowSqlConnection::create(['name' => 'Da modificare', 'config' => ['driver' => 'sqlite', 'database' => '/tmp/x.sqlite']]);

    $this->actingAs(adminUser())->get(route('admin.sql-connections.edit', $connection))->assertOk();
});

test('admin can create a global sql connection with an encrypted password', function () {
    $response = $this->actingAs(adminUser())->post(route('admin.sql-connections.store'), [
        'name' => 'Magazzino esterno',
        'workflow_id' => '',
        'driver' => 'mysql',
        'host' => 'db.example.com',
        'port' => 3306,
        'database' => 'magazzino',
        'username' => 'readonly',
        'password' => 'segreto123',
    ]);

    $response->assertRedirect(route('admin.sql-connections.index'));

    $connection = WorkflowSqlConnection::firstOrFail();
    expect($connection->workflow_id)->toBeNull();
    expect($connection->config['password'])->toBe('segreto123');

    $raw = DB::table('workflow_sql_connections')->first();
    expect($raw->config)->not->toContain('segreto123');
});

test('admin can scope a connection to a single workflow', function () {
    $workflow = wfWorkflowWithVersion();

    $this->actingAs(adminUser())->post(route('admin.sql-connections.store'), [
        'name' => 'Solo per questo flusso',
        'workflow_id' => $workflow->id,
        'driver' => 'sqlite',
        'database' => '/tmp/test.sqlite',
    ]);

    $connection = WorkflowSqlConnection::firstOrFail();
    expect($connection->workflow_id)->toBe($workflow->id);
});

test('leaving the password blank on update keeps the previous one', function () {
    $connection = WorkflowSqlConnection::create([
        'name' => 'Originale',
        'config' => ['driver' => 'mysql', 'database' => 'db', 'password' => 'vecchia-password'],
    ]);

    $this->actingAs(adminUser())->put(route('admin.sql-connections.update', $connection), [
        'name' => 'Rinominata',
        'workflow_id' => '',
        'driver' => 'mysql',
        'database' => 'db',
        'password' => '',
    ]);

    expect($connection->fresh()->config['password'])->toBe('vecchia-password');
    expect($connection->fresh()->name)->toBe('Rinominata');
});

test('admin can delete a connection', function () {
    $connection = WorkflowSqlConnection::create(['name' => 'Da eliminare', 'config' => ['driver' => 'sqlite', 'database' => '/tmp/x.sqlite']]);

    $this->actingAs(adminUser())->delete(route('admin.sql-connections.destroy', $connection))
        ->assertRedirect(route('admin.sql-connections.index'));

    expect(WorkflowSqlConnection::find($connection->id))->toBeNull();
});
