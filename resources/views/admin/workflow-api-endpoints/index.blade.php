@extends('layouts.admin')

@section('title', t('Endpoint API'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.api-endpoints.index') }}">{{ t('Endpoint API') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.api-endpoints.create') }}" class="btn btn-primary" data-testid="api-endpoint-create-link">
        {{ t('Nuovo endpoint') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="api-endpoints-status">
            @switch(session('status'))
                @case('api-endpoint-created')
                    {{ t('Endpoint creato correttamente.') }}
                    @break
                @case('api-endpoint-updated')
                    {{ t('Endpoint aggiornato correttamente.') }}
                    @break
                @case('api-endpoint-deleted')
                    {{ t('Endpoint eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table" data-testid="api-endpoints-table">
                <thead>
                    <tr>
                        <th>{{ t('Nome') }}</th>
                        <th>{{ t('URL base') }}</th>
                        <th>{{ t('Autenticazione') }}</th>
                        <th>{{ t('Ambito') }}</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($endpoints as $endpoint)
                        <tr>
                            <td>{{ $endpoint->name }}</td>
                            <td>{{ $endpoint->base_url }}</td>
                            <td>{{ $endpoint->config['auth_type'] ?? 'none' }}</td>
                            <td>{{ $endpoint->workflow?->name ?? t('Globale') }}</td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.api-endpoints.edit', $endpoint) }}" class="btn btn-sm btn-outline-primary">{{ t('Modifica') }}</a>
                                    <form method="POST" action="{{ route('admin.api-endpoints.destroy', $endpoint) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ t('Elimina') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-secondary">{{ t('Nessun endpoint API configurato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
