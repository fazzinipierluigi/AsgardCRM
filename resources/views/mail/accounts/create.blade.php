@extends('layouts.base')

@section('title', t('Nuova casella e-mail'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('mail.accounts.index') }}">{{ t('Le mie caselle e-mail') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('mail.accounts.create') }}">{{ t('Nuova casella e-mail') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="mail-account-form" class="btn btn-primary" data-testid="mail-account-submit">{{ t('Crea casella') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('mail.accounts.store') }}" method="POST" id="mail-account-form">
                @csrf
                @include('mail.accounts._form', ['account' => null, 'connectors' => $connectors, 'signatures' => $signatures])
            </form>
        </div>
    </div>
@endsection
