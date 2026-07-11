<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionController extends Controller
{
    /**
     * Display the permissions listing page.
     */
    public function index(): View
    {
        return view('admin.permissions.index');
    }

    /**
     * Serve the server-side datatable request for the permissions listing.
     */
    public function data(Request $request): JsonResponse
    {
        $permissions = Permission::with('roles')->select('id', 'key', 'name', 'description', 'created_at');

        $source = new EloquentSource;
        $source->apply($permissions, $request, null, ['key', 'name']);

        return $source->getResponse(function (Permission $permission) {
            return [
                'id' => $permission->id,
                'key' => $permission->key,
                'name' => $permission->name,
                'roles' => $permission->roles->pluck('name')->join(', '),
                'created_at' => $permission->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    /**
     * Show the form to create a new permission.
     */
    public function create(): View
    {
        return view('admin.permissions.create');
    }

    /**
     * Persist a new permission.
     */
    public function store(StorePermissionRequest $request): RedirectResponse
    {
        Permission::create($request->only('key', 'name', 'description'));

        return redirect()->route('admin.permissions.index')->with('status', 'permission-created');
    }

    /**
     * Show the form to edit an existing permission.
     */
    public function edit(Permission $permission): View
    {
        return view('admin.permissions.edit', ['permission' => $permission]);
    }

    /**
     * Update an existing permission.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update($request->only('key', 'name', 'description'));

        return redirect()->route('admin.permissions.index')->with('status', 'permission-updated');
    }

    /**
     * Delete a permission.
     */
    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('status', 'permission-deleted');
    }
}
