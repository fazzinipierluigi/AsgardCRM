@extends('layouts.admin')

@section('title', __('Modifica permesso'))

@section('content')
    <div class="page-header d-print-none">
        <h2 class="page-title">{{ __('Modifica permesso') }}</h2>
    </div>

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
