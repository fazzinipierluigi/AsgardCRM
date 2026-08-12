<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Admin;

use Fazzinipierluigi\CrmCore\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\UpdateEntityVisibilityRequest;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRoleVisibility;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EntityVisibilityController extends Controller
{
    /**
     * Show the per-role visibility level matrix for an entity. Admin
     * roles aren't listed — they always get full access, same as they
     * bypass Just A Gate's own permission checks.
     */
    public function edit(Entity $entity): View
    {
        $roles = Role::where('is_admin', false)->orderBy('name')->get();

        // ->pluck() hydrates the `level` cast (Eloquent applies casts even
        // to single-column plucks), so this yields EntityVisibilityLevel
        // instances — flatten to their raw string so the Blade view can
        // compare against option values without special-casing the type.
        $currentLevels = EntityRoleVisibility::where('entity_id', $entity->id)
            ->pluck('level', 'role_id')
            ->map(fn (EntityVisibilityLevel $level) => $level->value);

        return view('crm::admin.entities.visibility', [
            'entity' => $entity,
            'roles' => $roles,
            'currentLevels' => $currentLevels,
        ]);
    }

    /**
     * Save the submitted visibility level for each non-admin role.
     */
    public function update(UpdateEntityVisibilityRequest $request, Entity $entity): RedirectResponse
    {
        $roleIds = Role::where('is_admin', false)->pluck('id');

        foreach ($request->input('levels', []) as $roleId => $level) {
            if (! $roleIds->contains((int) $roleId)) {
                continue;
            }

            EntityRoleVisibility::updateOrCreate(
                ['entity_id' => $entity->id, 'role_id' => $roleId],
                ['level' => $level]
            );
        }

        return redirect()->route('admin.entities.index')->with('status', 'entity-visibility-updated');
    }
}
