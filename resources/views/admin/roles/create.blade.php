@extends('layouts.admin')

@section('title', __('Nuovo ruolo'))

@section('content')
    <div class="page-header d-print-none">
        <h2 class="page-title">{{ __('Nuovo ruolo') }}</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.roles.store') }}" method="POST">
                @csrf
                @include('admin.roles._form', ['role' => null, 'permissions' => $permissions, 'rolePermissionKeys' => []])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="role-submit">{{ __('Crea ruolo') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
