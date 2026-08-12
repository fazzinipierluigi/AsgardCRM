<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMenuRequest;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MenuController extends Controller
{
    /**
     * Show the sidebar/quick-access menu builder: every installed
     * entity — system ones (e.g. Calendario) included, they're just as
     * configurable as any custom entity — split into "in the main
     * menu" and "in the 'Altre entità' group" (see
     * layouts/base.blade.php), plus which ones also show up as
     * quick-access icons in the topbar.
     */
    public function edit(): View
    {
        $entities = Entity::where('is_installed', true)
            ->orderBy('menu_position')
            ->orderBy('name')
            ->get();

        return view('admin.menu.edit', [
            'visibleEntities' => $entities->where('show_in_menu', true)->values(),
            'hiddenEntities' => $entities->where('show_in_menu', false)->values(),
            'quickAccessEntities' => $entities->where('show_in_quick_access', true)->sortBy('quick_access_position')->values(),
        ]);
    }

    /**
     * Persist the submitted main-menu order/visibility and quick-access
     * order. Any installed entity missing from `visible` is implicitly
     * moved to "Altre entità"; any missing from `quick_access` is
     * implicitly removed from the topbar.
     */
    public function update(UpdateMenuRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            Entity::where('is_installed', true)
                ->update(['show_in_quick_access' => false, 'quick_access_position' => 0]);

            $visibleIds = collect($request->input('visible', []));
            $hiddenIds = Entity::where('is_installed', true)
                ->whereNotIn('id', $visibleIds)
                ->pluck('id');

            foreach ($visibleIds as $position => $id) {
                Entity::whereKey($id)->update(['show_in_menu' => true, 'menu_position' => $position]);
            }

            foreach ($hiddenIds as $position => $id) {
                Entity::whereKey($id)->update(['show_in_menu' => false, 'menu_position' => $position]);
            }

            foreach ($request->input('quick_access', []) as $position => $id) {
                Entity::whereKey($id)->update(['show_in_quick_access' => true, 'quick_access_position' => $position]);
            }
        });

        return redirect()->route('admin.menu.edit')->with('status', 'menu-updated');
    }
}
