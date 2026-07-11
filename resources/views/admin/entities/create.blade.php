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

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.entities.store') }}" method="POST">
                @csrf
                @include('admin.entities._form', ['entity' => null])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="entity-submit">{{ t('Crea entità') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
