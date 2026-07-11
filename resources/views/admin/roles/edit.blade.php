@extends('layouts.admin')

@section('title', __('Modifica ruolo'))

@section('content')
    <div class="page-header d-print-none">
        <h2 class="page-title">{{ __('Modifica ruolo') }}</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.roles.update', $role) }}" method="POST">
                @csrf
                @method('PUT')
                @include('admin.roles._form', ['role' => $role, 'permissions' => $permissions, 'rolePermissionKeys' => $rolePermissionKeys])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="role-submit">{{ __('Salva modifiche') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
