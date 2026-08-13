<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Admin;

use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\StoreUserRequest;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\UpdateUserRequest;
use Fazzinipierluigi\CrmCore\Models\LoginProvider;
use Fazzinipierluigi\JustAGate\Models\Role;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display the users listing page.
     */
    public function index(): View
    {
        return view('crm::admin.users.index');
    }

    /**
     * Serve the server-side datatable request for the users listing.
     */
    public function data(Request $request): JsonResponse
    {
        $userModel = config('crm.user_model');
        $users = $userModel::with('roles')->select('id', 'name', 'username', 'email', 'created_at');

        $source = new EloquentSource;
        $source->apply($users, $request, null, ['name', 'username', 'email']);

        return $source->getResponse(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->join(', '),
                'created_at' => $user->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    /**
     * Show the form to create a new user.
     */
    public function create(): View
    {
        return view('crm::admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
            'loginProviders' => LoginProvider::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Persist a new user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $userModel = config('crm.user_model');

        $user = $userModel::create([
            'name' => $request->string('name'),
            'username' => $request->string('username'),
            'email' => $request->string('email'),
            'phone' => $request->string('phone')->value() ?: null,
            'job_title' => $request->string('job_title')->value() ?: null,
            'password' => Hash::make($request->string('password')),
            'login_provider_id' => $request->integer('login_provider_id') ?: null,
            'provider_identifier' => $request->string('provider_identifier')->value() ?: null,
        ]);

        $user->syncRoles($this->roleIds($request));

        return redirect()->route('admin.users.index')->with('status', 'user-created');
    }

    /**
     * Show the form to edit an existing user.
     */
    public function edit(int $user): View
    {
        $userModel = config('crm.user_model');
        $user = $userModel::findOrFail($user);

        return view('crm::admin.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'userRoleIds' => $user->getRoles()->pluck('id')->all(),
            'loginProviders' => LoginProvider::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update an existing user.
     */
    public function update(UpdateUserRequest $request, int $user): RedirectResponse
    {
        $userModel = config('crm.user_model');
        $user = $userModel::findOrFail($user);

        $user->fill($request->only('name', 'username', 'email', 'phone', 'job_title'));
        $user->login_provider_id = $request->integer('login_provider_id') ?: null;
        $user->provider_identifier = $request->string('provider_identifier')->value() ?: null;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->string('password'));
        }

        $user->save();

        $user->syncRoles($this->roleIds($request));

        return redirect()->route('admin.users.index')->with('status', 'user-updated');
    }

    /**
     * Role IDs from the request, cast to int — Just A Gate's
     * Authorizable::resolveRole() only treats a genuine PHP int as an id
     * (is_int('2') is false), otherwise falling back to a slug lookup
     * that 404s (ModelNotFoundException) for a real form submission,
     * where every value arrives as a string over HTTP.
     *
     * @return array<int, int>
     */
    private function roleIds(Request $request): array
    {
        return array_map('intval', $request->input('roles', []));
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, int $user): RedirectResponse
    {
        if ($user === $request->user()->id) {
            return back()->with('error', 'Non puoi eliminare il tuo stesso utente.');
        }

        $userModel = config('crm.user_model');
        $userModel::findOrFail($user)->delete();

        return redirect()->route('admin.users.index')->with('status', 'user-deleted');
    }
}
