<ul class="nav nav-tabs" role="tablist">
    @foreach ($entity->tabs as $tab)
        <li class="nav-item" role="presentation">
            <button
                class="nav-link {{ $loop->first ? 'active' : '' }}"
                type="button"
                data-bs-toggle="tab"
                data-bs-target="#entity-tab-{{ $tab->id }}"
                role="tab"
            >{{ $tab->name }}</button>
        </li>
    @endforeach
</ul>

<div class="tab-content pt-3">
    @foreach ($entity->tabs as $tab)
        <div class="tab-pane {{ $loop->first ? 'show active' : '' }}" id="entity-tab-{{ $tab->id }}" role="tabpanel">
            <div class="row row-cards">
                @foreach ($tab->cards as $card)
                    <div class="col-12">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">{{ $card->name }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach ($card->fields as $field)
                                        @include('entities._field_input', ['field' => $field, 'record' => $record, 'relationOptions' => $relationOptions, 'entity' => $entity])
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
