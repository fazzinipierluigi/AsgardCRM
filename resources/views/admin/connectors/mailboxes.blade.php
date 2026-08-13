@extends('layouts.admin')

@section('title', t('Mailbox utenti'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.connectors.index') }}">{{ t('Connettori') }}</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.connectors.edit', $connector) }}">{{ $connector->name }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.connectors.mailboxes.edit', $connector) }}">{{ t('Mailbox utenti') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="connector-mailboxes-form" class="btn btn-primary" data-testid="connector-mailboxes-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            @if (session('status') === 'connector-mailboxes-updated')
                <div class="alert alert-success" data-testid="connector-mailboxes-status">{{ t('Impostazioni aggiornate.') }}</div>
            @endif

            <form action="{{ route('admin.connectors.mailboxes.update', $connector) }}" method="POST" id="connector-mailboxes-form">
                @csrf
                @method('PUT')

                <table class="table" data-testid="connector-mailboxes-table">
                    <thead>
                        <tr>
                            <th>{{ t('Utente') }}</th>
                            <th>{{ t('Email mailbox') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $mailboxUser)
                            <tr data-testid="connector-mailbox-row-{{ $mailboxUser->id }}">
                                <td>{{ $mailboxUser->name }}</td>
                                <td>
                                    <input
                                        type="email"
                                        class="form-control @error("mailboxes.{$mailboxUser->id}") is-invalid @enderror"
                                        name="mailboxes[{{ $mailboxUser->id }}]"
                                        value="{{ old("mailboxes.{$mailboxUser->id}", $currentMailboxes[$mailboxUser->id] ?? '') }}"
                                    >
                                    @error("mailboxes.{$mailboxUser->id}")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </form>
        </div>
    </div>
@endsection
