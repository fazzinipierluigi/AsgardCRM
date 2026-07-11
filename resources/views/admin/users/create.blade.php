@extends('layouts.admin')

@section('title', t('Nuovo utente'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.users.index') }}">{{ t('Utenti') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.users.create') }}">{{ t('Nuovo utente') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                @include('admin.users._form', ['user' => null, 'roles' => $roles, 'userRoleIds' => []])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="user-submit">{{ t('Crea utente') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
