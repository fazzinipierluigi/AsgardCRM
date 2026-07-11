@extends('layouts.admin')

@section('title', t('Modifica traduzione'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.translations.index') }}">{{ t('Traduzioni') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.translations.edit', $translation) }}">{{ t('Modifica traduzione') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.translations.update', $translation) }}" method="POST">
                @csrf
                @method('PUT')
                @php $keyReadonly = true; @endphp
                @include('admin.translations._form')

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="translation-submit">{{ t('Salva modifiche') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
