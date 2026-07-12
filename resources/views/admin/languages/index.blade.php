@extends('layouts.admin')

@section('title', t('Lingue'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.languages.index') }}">{{ t('Lingue') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="language-form" class="btn btn-primary" data-testid="language-submit">{{ t('Aggiungi lingua') }}</button>
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ t('Lingue disponibili') }}</h3>
                </div>
                <div class="card-body">
                    @if (session('status') === 'language-created')
                        <div class="alert alert-success" data-testid="languages-status">{{ t('Lingua creata correttamente.') }}</div>
                    @endif
                    @if (session('status') === 'language-deleted')
                        <div class="alert alert-success" data-testid="languages-status">{{ t('Lingua eliminata correttamente.') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger" data-testid="languages-error">{{ session('error') }}</div>
                    @endif

                    <table class="table table-vcenter" data-testid="languages-table">
                        <thead>
                            <tr>
                                <th>{{ t('Codice') }}</th>
                                <th>{{ t('Nome') }}</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($languages as $language)
                                <tr data-testid="language-row-{{ $language->code }}">
                                    <td>{{ $language->code }}</td>
                                    <td>{{ $language->name }}</td>
                                    <td>
                                        <form action="{{ route('admin.languages.destroy', $language) }}" method="POST" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-testid="language-destroy-{{ $language->code }}">{{ t('Elimina') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ t('Aggiungi lingua') }}</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.languages.store') }}" method="POST" id="language-form">
                        @csrf

                        <div class="mb-3">
                            <label for="code" class="form-label">{{ t('Codice') }}</label>
                            <input
                                type="text"
                                id="code"
                                name="code"
                                value="{{ old('code') }}"
                                class="form-control @error('code') is-invalid @enderror"
                                placeholder="es. fr"
                            >
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ t('Nome') }}</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="es. Français"
                            >
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
