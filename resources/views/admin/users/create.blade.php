@extends('layouts.admin')

@section('title', __('Nuovo utente'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.users.index') }}">{{ __('Utenti') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.users.create') }}">{{ __('Nuovo utente') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                @include('admin.users._form', ['user' => null, 'roles' => $roles, 'userRoleIds' => []])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="user-submit">{{ __('Crea utente') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
