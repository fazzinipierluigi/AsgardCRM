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

@section('buttons')
    <button type="submit" form="user-form" class="btn btn-primary" data-testid="user-submit">{{ t('Crea utente') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.users.store') }}" method="POST" id="user-form">
                @csrf
                @include('admin.users._form', ['user' => null, 'roles' => $roles, 'userRoleIds' => []])
            </form>
        </div>
    </div>
@endsection
