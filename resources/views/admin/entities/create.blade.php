@extends('layouts.admin')

@section('title', t('Nuova entità'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.entities.create') }}">{{ t('Nuova entità') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="entity-form" class="btn btn-primary" data-testid="entity-submit">{{ t('Crea entità') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.entities.store') }}" method="POST" id="entity-form">
                @csrf
                @include('crm::admin.entities._form', ['entity' => null])
            </form>
        </div>
    </div>
@endsection
