<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers;

use Fazzinipierluigi\CrmCore\Http\Requests\UpdatePreferencesRequest;
use Fazzinipierluigi\CrmCore\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the current user's personal settings form.
     */
    public function edit(): View
    {
        $user = auth()->user();

        $preferences = collect(preferences())
            ->mapWithKeys(fn (array $preference, string $key) => [
                $key => $user->getSetting($key, $preference['default']),
            ]);

        return view('crm::settings.edit', ['user' => $user, 'preferences' => $preferences]);
    }

    /**
     * Update the current user's personal settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->only('name', 'email'));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->string('password'));
        }

        $user->save();

        return back()->with('status', 'settings-updated');
    }

    /**
     * Update the current user's preferences (date format, language, number
     * format, theme).
     */
    public function updatePreferences(UpdatePreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();

        foreach (array_keys(preferences()) as $key) {
            $user->setSetting($key, $request->string($key)->toString());
        }

        return back()->with('status', 'preferences-updated');
    }
}
