@extends('layouts.base')

@section('title', t('Modifica casella e-mail'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('mail.accounts.index') }}">{{ t('Le mie caselle e-mail') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('mail.accounts.edit', $account) }}">{{ $account->name }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="mail-account-form" class="btn btn-primary" data-testid="mail-account-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    @if (session('status') === 'mail-account-oauth-connected')
        <div class="alert alert-success" data-testid="mail-account-oauth-connected">{{ t('Casella connessa correttamente.') }}</div>
    @endif

    @error('auth_method')
        <div class="alert alert-danger" data-testid="mail-account-oauth-error">{{ $message }}</div>
    @enderror

    <div class="card">
        <div class="card-body">
            <form action="{{ route('mail.accounts.update', $account) }}" method="POST" id="mail-account-form">
                @csrf
                @method('PUT')
                @include('mail.accounts._form', ['account' => $account, 'connectors' => $connectors, 'signatures' => $signatures])
            </form>
        </div>
    </div>
@endsection
