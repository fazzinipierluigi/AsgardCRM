@extends('layouts.base')

@section('title', t('I miei task'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('workflow-tasks.index') }}">{{ t('I miei task') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div id="workflow-tasks-grid" data-testid="workflow-tasks-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var grid = new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                columns: [
                    { id: 'workflow', index: 'workflow', text: @json(t('Workflow')), sortable: true },
                    { id: 'node', index: 'node', text: @json(t('Task')), sortable: true },
                    { id: 'created_at', index: 'created_at', text: @json(t('Assegnato il')), sortable: true },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        render: function (params) {
                            var url = @json(route('workflow-tasks.index')) + '/' + params.value;
                            return '<a href="' + url + '" class="btn btn-sm btn-primary">' + @json(t('Completa')) + '</a>';
                        },
                    },
                ],
                serverAdapter: {
                    url: @json(route('workflow-tasks.data')),
                    method: 'GET',
                },
            }).render('#workflow-tasks-grid');
        });
    </script>
@endsection
