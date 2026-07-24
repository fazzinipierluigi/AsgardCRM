@extends('layouts.admin')

@section('title', t('Nuova connessione SQL'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.sql-connections.index') }}">{{ t('Connessioni SQL') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.sql-connections.create') }}">{{ t('Nuova connessione') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="sql-connection-form" class="btn btn-primary" data-testid="sql-connection-submit">{{ t('Crea connessione') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.sql-connections.store') }}" method="POST" id="sql-connection-form">
                @csrf
                @include('admin.workflow-sql-connections._form', ['connection' => null, 'workflows' => $workflows])
            </form>
        </div>
    </div>
@endsection
