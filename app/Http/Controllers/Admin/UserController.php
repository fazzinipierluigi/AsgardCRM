<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
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
        return view('admin.users.index');
    }

    /**
     * Serve the server-side datatable request for the users listing.
     */
    public function data(Request $request): JsonResponse
    {
        $users = User::with('roles')->select('id', 'name', 'username', 'email', 'created_at');

        $source = new EloquentSource;
        $source->apply($users, $request, null, ['name', 'username', 'email']);

        return $source->getResponse(function (User $user) {
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
        return view('admin.users.create', ['roles' => Role::orderBy('name')->get()]);
    }

    /**
     * Persist a new user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'username' => $request->string('username'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
        ]);

        $user->syncRoles($request->input('roles', []));

        return redirect()->route('admin.users.index')->with('status', 'user-created');
    }

    /**
     * Show the form to edit an existing user.
     */
    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'userRoleIds' => $user->getRoles()->pluck('id')->all(),
        ]);
    }

    /**
     * Update an existing user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->only('name', 'username', 'email'));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->string('password'));
        }

        $user->save();

        $user->syncRoles($request->input('roles', []));

        return redirect()->route('admin.users.index')->with('status', 'user-updated');
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Non puoi eliminare il tuo stesso utente.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'user-deleted');
    }
}
