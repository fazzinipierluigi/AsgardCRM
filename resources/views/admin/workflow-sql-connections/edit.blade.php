@extends('layouts.admin')

@section('title', t('Modifica connessione SQL'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.sql-connections.index') }}">{{ t('Connessioni SQL') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.sql-connections.edit', $connection) }}">{{ t('Modifica connessione') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="sql-connection-form" class="btn btn-primary" data-testid="sql-connection-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.sql-connections.update', $connection) }}" method="POST" id="sql-connection-form">
                @csrf
                @method('PUT')
                @include('admin.workflow-sql-connections._form', ['connection' => $connection, 'workflows' => $workflows])
            </form>
        </div>
    </div>
@endsection
