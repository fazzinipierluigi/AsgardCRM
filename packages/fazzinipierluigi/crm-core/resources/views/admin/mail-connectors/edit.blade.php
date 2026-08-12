@extends('layouts.admin')

@section('title', t('Modifica connettore'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.mail-connectors.index') }}">{{ t('Connettori e-mail aziendali') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.mail-connectors.edit', $connector) }}">{{ $connector->name }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="mail-connector-form" class="btn btn-primary" data-testid="mail-connector-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.mail-connectors.update', $connector) }}" method="POST" id="mail-connector-form">
                @csrf
                @method('PUT')
                @include('crm::admin.mail-connectors._form', ['connector' => $connector])
            </form>
        </div>
    </div>
@endsection
