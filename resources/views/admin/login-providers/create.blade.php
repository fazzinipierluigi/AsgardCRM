@extends('layouts.admin')

@section('title', t('Nuovo provider'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.login-providers.index') }}">{{ t('Login provider') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.login-providers.create') }}">{{ t('Nuovo provider') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="login-provider-form" class="btn btn-primary" data-testid="login-provider-submit">{{ t('Crea provider') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.login-providers.store') }}" method="POST" id="login-provider-form">
                @csrf
                @include('crm::admin.login-providers._form', ['loginProvider' => null])
            </form>
        </div>
    </div>
@endsection
