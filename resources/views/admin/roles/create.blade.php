@extends('layouts.admin')

@section('title', t('Nuovo ruolo'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.roles.index') }}">{{ t('Ruoli') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.roles.create') }}">{{ t('Nuovo ruolo') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.roles.store') }}" method="POST">
                @csrf
                @include('admin.roles._form', ['role' => null])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="role-submit">{{ t('Crea ruolo') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
