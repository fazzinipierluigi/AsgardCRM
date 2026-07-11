@extends('layouts.admin')

@section('title', t('Nuova traduzione'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.translations.index') }}">{{ t('Traduzioni') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.translations.create') }}">{{ t('Nuova traduzione') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.translations.store') }}" method="POST">
                @csrf
                @php $translation = null; @endphp
                @include('admin.translations._form')

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="translation-submit">{{ t('Crea traduzione') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
