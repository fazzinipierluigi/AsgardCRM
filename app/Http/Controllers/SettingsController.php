<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
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
        return view('settings.edit', ['user' => auth()->user()]);
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
}
