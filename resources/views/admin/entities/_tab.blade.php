@php
    $cards = $tab?->cards ?? collect();
    $tabLocked = ($entity?->is_installed ?? false) && $tab !== null;
@endphp
<div class="tab-pane repeatable-row {{ $active ? 'show active' : '' }}" id="tab-pane-{{ $tabToken }}" role="tabpanel" data-tab-token="{{ $tabToken }}">
    <div class="cards-container row row-cards">
        @foreach ($cards as $card)
            @include('admin.entities._card', ['tabToken' => $tabToken, 'cardToken' => $card->id, 'card' => $card, 'relationTargets' => $relationTargets, 'fieldTypes' => $fieldTypes, 'entity' => $entity])
        @endforeach
    </div>

    <div class="mt-2">
        <button type="button" class="btn btn-sm btn-outline-primary add-card-btn" data-testid="add-card-btn">{{ t('Aggiungi card') }}</button>
        @unless ($tabLocked)
            <button type="button" class="btn btn-sm btn-outline-danger tab-pane-remove-tab-btn">{{ t('Rimuovi tab') }}</button>
        @endunless
    </div>
</div>
