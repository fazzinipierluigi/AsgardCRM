<li
    class="list-group-item d-flex align-items-center gap-2 menu-item"
    data-entity-id="{{ $entity->id }}"
    data-entity-name="{{ $entity->name }}"
    data-entity-slug="{{ $entity->slug }}"
    data-testid="menu-item-{{ $entity->slug }}"
>
    <span class="menu-drag-handle px-1" style="cursor: move" title="{{ t('Trascina per riordinare') }}" data-testid="menu-drag-handle-{{ $entity->slug }}">⠿</span>
    <span class="menu-item-icon">
        @if ($entity->icon)
            {!! icon($entity->icon) !!}
        @endif
    </span>
    <span class="flex-fill">{{ $entity->name }}</span>
    <button type="button" class="btn btn-icon btn-sm menu-toggle-visibility-btn" title="{{ t('Sposta') }}" data-testid="menu-toggle-visibility-{{ $entity->slug }}">
        {!! icon('arrows-left-right') !!}
    </button>
    <button
        type="button"
        class="btn btn-icon btn-sm ms-2 menu-toggle-quick-access-btn {{ $entity->show_in_quick_access ? 'text-warning' : '' }}"
        title="{{ t('Accesso rapido') }}"
        data-testid="menu-toggle-quick-access-{{ $entity->slug }}"
    >
        {!! icon('star') !!}
    </button>
</li>
