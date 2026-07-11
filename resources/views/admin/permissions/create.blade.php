@extends('layouts.admin')

@section('title', __('Nuovo permesso'))

@section('content')
    <div class="page-header d-print-none">
        <h2 class="page-title">{{ __('Nuovo permesso') }}</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.permissions.store') }}" method="POST">
                @csrf
                @php $permission = null; @endphp
                @include('admin.permissions._form')

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="permission-submit">{{ __('Crea permesso') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
