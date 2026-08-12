@extends('layouts.admin')

@section('title', t('Nuovo connettore'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.connectors.index') }}">{{ t('Connettori') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.connectors.create') }}">{{ t('Nuovo connettore') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="connector-form" class="btn btn-primary" data-testid="connector-submit">{{ t('Crea connettore') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.connectors.store') }}" method="POST" id="connector-form">
                @csrf
                @include('crm::admin.connectors._form', ['connector' => null])
            </form>
        </div>
    </div>
@endsection
