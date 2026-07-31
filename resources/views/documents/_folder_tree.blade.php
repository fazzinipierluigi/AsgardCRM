@php
    $folder = $node['folder'];
    $isActive = $currentFolder?->id === $folder->id;
@endphp

<a
    href="{{ route('documents.index', ['folder' => $folder->id]) }}"
    class="list-group-item list-group-item-action d-flex align-items-center {{ $isActive ? 'active' : '' }}"
    style="padding-left: {{ 1 + $depth * 1.25 }}rem;"
    data-testid="document-folder-tree-item-{{ $folder->id }}"
>
    <span class="me-2" style="width: 1.25rem;">{!! icon($isActive ? 'folder-open' : 'folder') !!}</span>
    {{ $folder->name }}
</a>

@foreach ($node['children'] as $child)
    @include('documents._folder_tree', ['node' => $child, 'currentFolder' => $currentFolder, 'depth' => $depth + 1])
@endforeach
