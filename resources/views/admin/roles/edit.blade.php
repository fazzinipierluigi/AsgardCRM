@extends('layouts.admin')

@section('title', __('Modifica ruolo'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.roles.index') }}">{{ __('Ruoli') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.roles.edit', $role) }}">{{ __('Modifica ruolo') }}</a>
    </li>
@endsection

@section('content')
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
