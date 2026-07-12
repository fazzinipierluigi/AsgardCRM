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

@section('buttons')
    <button type="submit" form="translation-form" class="btn btn-primary" data-testid="translation-submit">{{ t('Crea traduzione') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.translations.store') }}" method="POST" id="translation-form">
                @csrf
                @php $translation = null; @endphp
                @include('admin.translations._form')
            </form>
        </div>
    </div>
@endsection
