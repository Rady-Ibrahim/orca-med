@props([
    'label'  => '',
    'value'  => 0,
    'color'  => 'blue',
    'icon'   => null,
    'suffix' => '',
])

@php
    $colorMap = [
        'blue'   => ['bg' => 'bg-blue-50',   'icon' => 'bg-blue-100 text-blue-600',   'val' => 'text-blue-700'],
        'green'  => ['bg' => 'bg-green-50',  'icon' => 'bg-green-100 text-green-600', 'val' => 'text-green-700'],
        'amber'  => ['bg' => 'bg-amber-50',  'icon' => 'bg-amber-100 text-amber-600', 'val' => 'text-amber-700'],
        'violet' => ['bg' => 'bg-violet-50', 'icon' => 'bg-violet-100 text-violet-600','val' => 'text-violet-700'],
        'slate'  => ['bg' => 'bg-slate-50',  'icon' => 'bg-slate-100 text-slate-600', 'val' => 'text-slate-700'],
    ];
    $c = $colorMap[$color] ?? $colorMap['blue'];
@endphp

<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 flex items-center gap-4">
    @if($icon)
        <div class="w-12 h-12 rounded-xl {{ $c['icon'] }} flex items-center justify-center shrink-0 text-xl">
            {{ $icon }}
        </div>
    @endif
    <div class="min-w-0">
        <div class="text-sm text-slate-500 font-medium">{{ $label }}</div>
        <div class="text-2xl font-bold {{ $c['val'] }} mt-0.5">
            {{ number_format((float)$value) }}{{ $suffix ? ' '.$suffix : '' }}
        </div>
    </div>
</div>
