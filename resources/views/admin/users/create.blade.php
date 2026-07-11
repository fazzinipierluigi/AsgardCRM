@extends('layouts.admin')

@section('title', __('Nuovo utente'))

@section('content')
    <div class="page-header d-print-none">
        <h2 class="page-title">{{ __('Nuovo utente') }}</h2>
    </div>

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
