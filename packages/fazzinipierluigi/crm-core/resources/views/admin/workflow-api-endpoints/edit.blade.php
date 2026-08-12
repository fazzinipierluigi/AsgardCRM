@extends('layouts.admin')

@section('title', t('Modifica endpoint API'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.api-endpoints.index') }}">{{ t('Endpoint API') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.api-endpoints.edit', $endpoint) }}">{{ t('Modifica endpoint') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="api-endpoint-form" class="btn btn-primary" data-testid="api-endpoint-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.api-endpoints.update', $endpoint) }}" method="POST" id="api-endpoint-form">
                @csrf
                @method('PUT')
                @include('crm::admin.workflow-api-endpoints._form', ['endpoint' => $endpoint, 'workflows' => $workflows])
            </form>
        </div>
    </div>
@endsection
