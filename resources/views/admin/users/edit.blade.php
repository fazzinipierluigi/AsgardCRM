@extends('layouts.admin')

@section('title', __('Modifica utente'))

@section('content')
    <div class="page-header d-print-none">
        <h2 class="page-title">{{ __('Modifica utente') }}</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')
                @include('admin.users._form', ['user' => $user, 'roles' => $roles, 'userRoleIds' => $userRoleIds])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="user-submit">{{ __('Salva modifiche') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
