<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers;

use Fazzinipierluigi\CrmCore\Http\Requests\UpdateCalendarSharesRequest;
use Fazzinipierluigi\CrmCore\Models\CalendarShare;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lets a user manage their own outgoing CalendarShare grants — who else
 * can see (and optionally edit) their calendar events. Distinct from
 * admin\EntityVisibilityController's per-role matrix: this is personal,
 * self-service, and per-user (see CalendarAuthorizer for how the two
 * compose).
 */
class CalendarSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorizeAccess();
        $owner = $request->user();

        $shareableUsers = config('crm.user_model')::where('id', '!=', $owner->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (CrmUser $user) => $user->can('entity_calendario.index'))
            ->values();

        $currentShares = CalendarShare::where('owner_user_id', $owner->id)
            ->get()
            ->mapWithKeys(fn (CalendarShare $share) => [$share->shared_with_user_id => $share->permission->value]);

        return view('crm::calendar.settings', [
            'shareableUsers' => $shareableUsers,
            'currentShares' => $currentShares,
        ]);
    }

    public function updateShares(UpdateCalendarSharesRequest $request): RedirectResponse
    {
        $this->authorizeAccess();
        $owner = $request->user();
        $shareableUserIds = config('crm.user_model')::where('id', '!=', $owner->id)->pluck('id');

        foreach ($request->input('shares', []) as $userId => $permission) {
            if (! $shareableUserIds->contains((int) $userId)) {
                continue;
            }

            if ($permission === 'none') {
                CalendarShare::where('owner_user_id', $owner->id)->where('shared_with_user_id', $userId)->delete();

                continue;
            }

            CalendarShare::updateOrCreate(
                ['owner_user_id' => $owner->id, 'shared_with_user_id' => $userId],
                ['permission' => $permission]
            );
        }

        return redirect()->route('calendar.settings.edit')->with('status', 'calendar-shares-updated');
    }

    /**
     * Sharing is only meaningful for a user who can use the calendar at
     * all — same flat entity_calendario.index permission that gates the
     * calendar UI itself.
     */
    private function authorizeAccess(): void
    {
        $entity = Entity::where('slug', 'calendario')->first();

        if ($entity === null || ! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot('entity_calendario.index')) {
            abort(403);
        }
    }
}
