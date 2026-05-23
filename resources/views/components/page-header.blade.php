@props([
    'title'    => '',
    'subtitle' => null,
])

<div class="flex items-start justify-between py-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-sm text-slate-500 mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div class="flex items-center gap-2 shrink-0">
            {{ $slot }}
        </div>
    @endif
</div>
