@extends('layouts.admin')

@section('title', t('Modifica connettore'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.connectors.index') }}">{{ t('Connettori') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.connectors.edit', $connector) }}">{{ $connector->name }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.connectors.mailboxes.edit', $connector) }}" class="btn btn-outline-secondary" data-testid="connector-mailboxes-link">
        {{ t('Mailbox utenti') }}
    </a>
    <button type="submit" form="connector-form" class="btn btn-primary" data-testid="connector-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.connectors.update', $connector) }}" method="POST" id="connector-form">
                @csrf
                @method('PUT')
                @include('admin.connectors._form', ['connector' => $connector])
            </form>
        </div>
    </div>
@endsection
