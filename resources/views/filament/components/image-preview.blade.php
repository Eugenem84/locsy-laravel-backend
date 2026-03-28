@props([
    'url' => null,
])

@if ($url)
    <div class="mb-4">
        <img src="{{ $url }}" alt="Image Preview" class="max-w-full h-auto rounded-lg">
    </div>
@endif
