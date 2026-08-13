@extends('layouts.admin')

@section('title', t('Connessioni SQL'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.sql-connections.index') }}">{{ t('Connessioni SQL') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.sql-connections.create') }}" class="btn btn-primary" data-testid="sql-connection-create-link">
        {{ t('Nuova connessione') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="sql-connections-status">
            @switch(session('status'))
                @case('sql-connection-created')
                    {{ t('Connessione creata correttamente.') }}
                    @break
                @case('sql-connection-updated')
                    {{ t('Connessione aggiornata correttamente.') }}
                    @break
                @case('sql-connection-deleted')
                    {{ t('Connessione eliminata correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table" data-testid="sql-connections-table">
                <thead>
                    <tr>
                        <th>{{ t('Nome') }}</th>
                        <th>{{ t('Driver') }}</th>
                        <th>{{ t('Ambito') }}</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($connections as $connection)
                        <tr>
                            <td>{{ $connection->name }}</td>
                            <td>{{ $connection->config['driver'] ?? '' }}</td>
                            <td>{{ $connection->workflow?->name ?? t('Globale') }}</td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.sql-connections.edit', $connection) }}" class="btn btn-sm btn-outline-primary">{{ t('Modifica') }}</a>
                                    <form method="POST" action="{{ route('admin.sql-connections.destroy', $connection) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ t('Elimina') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-secondary">{{ t('Nessuna connessione SQL configurata.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
