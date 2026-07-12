@php
    $selectedIcon = old('icon', $entity?->icon);
@endphp

<div class="row">
    <div class="col-md-8 mb-3">
        <label for="name" class="form-label">{{ t('Nome') }}</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $entity?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="icon" class="form-label">{{ t('Icona') }}</label>
        <select
            id="icon"
            name="icon"
            class="form-select @error('icon') is-invalid @enderror"
            data-tom-select-manual
            data-testid="entity-icon-select"
        >
            <option value="">{{ t('Nessuna icona') }}</option>
            @foreach (icon_names() as $iconName)
                <option value="{{ $iconName }}" @selected($selectedIcon === $iconName)>{{ $iconName }}</option>
            @endforeach
        </select>
        <small class="form-hint">{{ t('Icona Tabler mostrata nel menu.') }}</small>
        @error('icon')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

@if ($entity)
    <div class="mb-3">
        <label for="slug" class="form-label">{{ t('Slug') }}</label>
        <input type="text" id="slug" value="{{ $entity->slug }}" class="form-control" readonly>
        <small class="form-hint">{{ t('Lo slug non può essere modificato dopo la creazione.') }}</small>
    </div>
@endif

<script>
    (function () {
        function renderIconOption(data, escape) {
            if (!data.value) {
                return '<div>' + escape(data.text) + '</div>';
            }

            return '<div class="d-flex align-items-center gap-2">'
                + '<img src="' + window.ICONS_BASE_URL + '/{{ config('icons.default_variant') }}/' + escape(data.value) + '" width="20" height="20" alt="" loading="lazy">'
                + '<span>' + escape(data.text) + '</span>'
                + '</div>';
        }

        document.addEventListener('DOMContentLoaded', function () {
            window.tomSelect('#icon', {
                render: {
                    option: renderIconOption,
                    item: renderIconOption,
                },
            });
        });
    })();
</script>
