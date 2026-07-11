@extends('layouts.base')

@section('title', t('Dashboard'))

@section('content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h2 class="mb-0">{{ t('Welcome, :name', ['name' => auth()->user()->name]) }}</h2>
                </div>
            </div>
        </div>
    </div>
@endsection
