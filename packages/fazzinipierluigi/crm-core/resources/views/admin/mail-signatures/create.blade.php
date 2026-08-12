@extends('layouts.admin')

@section('title', t('Nuova firma'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.mail-signatures.index') }}">{{ t('Firme e-mail') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.mail-signatures.create') }}">{{ t('Nuova firma') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="mail-signature-form" class="btn btn-primary" data-testid="mail-signature-submit">{{ t('Crea firma') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.mail-signatures.store') }}" method="POST" id="mail-signature-form">
                @csrf
                @include('crm::admin.mail-signatures._form', ['signature' => null])
            </form>
        </div>
    </div>

    @vite('resources/js/mail-signature-form.js', 'vendor/crm')
@endsection
