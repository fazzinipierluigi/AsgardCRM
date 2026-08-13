@extends('layouts.admin')

@section('title', t('Nuovo connettore'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.mail-connectors.index') }}">{{ t('Connettori e-mail aziendali') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.mail-connectors.create') }}">{{ t('Nuovo connettore') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="mail-connector-form" class="btn btn-primary" data-testid="mail-connector-submit">{{ t('Crea connettore') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.mail-connectors.store') }}" method="POST" id="mail-connector-form">
                @csrf
                @include('crm::admin.mail-connectors._form', ['connector' => null])
            </form>
        </div>
    </div>
@endsection
