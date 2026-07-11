<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    /**
     * Display the roles listing page.
     */
    public function index(): View
    {
        return view('admin.roles.index');
    }

    /**
     * Serve the server-side datatable request for the roles listing.
     */
    public function data(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')->select('id', 'name', 'slug', 'is_admin', 'is_system', 'created_at');

        $source = new EloquentSource;
        $source->apply($roles, $request, null, ['name', 'slug']);

        return $source->getResponse(function (Role $role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'is_admin' => $role->is_admin,
                'is_system' => $role->is_system,
                'permissions_count' => $role->permissions->count(),
                'created_at' => $role->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    /**
     * Show the form to create a new role.
     */
    public function create(): View
    {
        return view('admin.roles.create');
    }

    /**
     * Persist a new role. The slug is auto-generated from the name.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Role::create([
            'name' => $request->string('name'),
            'slug' => $this->uniqueSlug($request->string('name')),
        ]);

        return redirect()->route('admin.roles.index')->with('status', 'role-created');
    }

    /**
     * Show the form to edit an existing role.
     */
    public function edit(Role $role): View|RedirectResponse
    {
        if ($role->is_admin) {
            return redirect()->route('admin.roles.index')->with('error', 'Il ruolo amministratore non può essere modificato.');
        }

        return view('admin.roles.edit', ['role' => $role]);
    }

    /**
     * Update an existing role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->is_admin) {
            return back()->with('error', 'Il ruolo amministratore non può essere modificato.');
        }

        $role->name = $request->string('name');

        if (! $role->is_system) {
            $role->slug = $request->string('slug');
        }

        $role->save();

        return redirect()->route('admin.roles.index')->with('status', 'role-updated');
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'Non è possibile eliminare un ruolo di sistema.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'role-deleted');
    }

    /**
     * Show the form to manage a role's assigned permissions.
     */
    public function editPermissions(Role $role): View|RedirectResponse
    {
        if ($role->is_admin) {
            return redirect()->route('admin.roles.index')->with('error', 'Il ruolo amministratore ha già accesso completo: non è possibile assegnargli permessi.');
        }

        return view('admin.roles.permissions', [
            'role' => $role,
            'permissions' => $this->groupedPermissions(),
            'rolePermissionKeys' => $role->permissions->pluck('key')->all(),
        ]);
    }

    /**
     * Sync a role's assigned permissions.
     */
    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        if ($role->is_admin) {
            return back()->with('error', 'Il ruolo amministratore ha già accesso completo: non è possibile assegnargli permessi.');
        }

        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', 'role-permissions-updated');
    }

    /**
     * Get all permissions grouped by their key prefix (e.g. "user" from "user.index").
     *
     * @return Collection<string, Collection>
     */
    private function groupedPermissions()
    {
        return Permission::orderBy('key')->get()->groupBy(
            fn (Permission $permission) => explode('.', $permission->key)[0]
        );
    }

    /**
     * Slugify the given name, appending a numeric suffix until it's unique.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
