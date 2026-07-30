@php
    $steps = ['Welcome', 'Database', 'Administrator', 'Finish'];
@endphp

<ul class="wizard-steps steps steps-counter steps-horizontal mb-4">
    @foreach ($steps as $index => $label)
        <li class="step-item {{ $index === $current ? 'active' : '' }}">
            {{ $label }}
        </li>
    @endforeach
</ul>
