@extends('layouts.admin')

@section('title', __('Nuovo permesso'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.permissions.index') }}">{{ __('Permessi') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.permissions.create') }}">{{ __('Nuovo permesso') }}</a>
    </li>
@endsection

@section('content')
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
