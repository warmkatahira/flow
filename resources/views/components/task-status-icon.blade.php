@props(['status'])

@php
    use App\Enums\TaskStatus;
@endphp

<svg viewBox="0 0 20 20" aria-hidden="true" {{ $attributes->merge(['class' => 'shrink-0 '.$status->textClass()]) }}>
    @if ($status === TaskStatus::Todo)
        <circle cx="10" cy="10" r="8.25" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.6 2.6" />
        <path d="M6.5 10.5l2.5 2.5 4.5-5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    @else
        <circle cx="10" cy="10" r="9" fill="currentColor" />

        @switch ($status)
            @case (TaskStatus::Doing)
                <path d="M8 6.5v7l5.5-3.5z" fill="white" />
                @break
            @case (TaskStatus::Done)
                <path d="M6 10.5l2.8 2.8L14 7.5" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                @break
            @case (TaskStatus::Canceled)
                <path d="M7 7l6 6M13 7l-6 6" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" />
                @break
            @case (TaskStatus::OnHold)
                <path d="M8 6.5v7M12 6.5v7" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" />
                @break
        @endswitch
    @endif
</svg>
