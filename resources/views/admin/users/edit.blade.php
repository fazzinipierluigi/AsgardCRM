@extends('layouts.admin')

@section('title', __('Modifica utente'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.users.index') }}">{{ __('Utenti') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.users.edit', $user) }}">{{ __('Modifica utente') }}</a>
    </li>
@endsection

@section('content')
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
