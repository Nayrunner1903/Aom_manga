@props([
    'status',
])

@if ($status)
    <div role="status" {{ $attributes->merge(['class' => 'manga-notice font-medium text-sm']) }}>
        {{ $status }}
    </div>
@endif
