@extends('layouts.admin')

@section('title', __('Nuovo ruolo'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.roles.index') }}">{{ __('Ruoli') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.roles.create') }}">{{ __('Nuovo ruolo') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.roles.store') }}" method="POST">
                @csrf
                @include('admin.roles._form', ['role' => null])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="role-submit">{{ __('Crea ruolo') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
