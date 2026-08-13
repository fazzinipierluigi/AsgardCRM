@extends('layouts.admin')

@section('title', t('Modifica ruolo'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.roles.index') }}">{{ t('Ruoli') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.roles.edit', $role) }}">{{ t('Modifica ruolo') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.roles.permissions.edit', $role) }}" class="btn btn-link" data-testid="role-manage-permissions-link">
        {{ t('Gestisci permessi') }}
    </a>
    <button type="submit" form="role-form" class="btn btn-primary" data-testid="role-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.roles.update', $role) }}" method="POST" id="role-form">
                @csrf
                @method('PUT')
                @include('crm::admin.roles._form', ['role' => $role])
            </form>
        </div>
    </div>
@endsection
