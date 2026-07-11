@extends('layouts.admin')

@section('title', __('Nuova traduzione'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.translations.index') }}">{{ __('Traduzioni') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.translations.create') }}">{{ __('Nuova traduzione') }}</a>
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
                    <button type="submit" class="btn btn-primary" data-testid="translation-submit">{{ __('Crea traduzione') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
