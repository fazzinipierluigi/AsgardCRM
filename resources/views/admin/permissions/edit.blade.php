@extends('layouts.admin')

@section('title', __('Modifica permesso'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.permissions.index') }}">{{ __('Permessi') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.permissions.edit', $permission) }}">{{ __('Modifica permesso') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.permissions.update', $permission) }}" method="POST">
                @csrf
                @method('PUT')
                @include('admin.permissions._form')

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="permission-submit">{{ __('Salva modifiche') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
