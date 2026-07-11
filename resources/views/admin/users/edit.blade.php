@extends('layouts.admin')

@section('title', t('Modifica utente'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.users.index') }}">{{ t('Utenti') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.users.edit', $user) }}">{{ t('Modifica utente') }}</a>
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
                    <button type="submit" class="btn btn-primary" data-testid="user-submit">{{ t('Salva modifiche') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
