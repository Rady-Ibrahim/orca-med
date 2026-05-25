@props([
    'paginator' => null,
])

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

    {{-- Filter bar (optional slot) --}}
    @if(isset($filters))
        <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
            {{ $filters }}
        </div>
    @endif

    {{-- Table --}}
    <div class="overflow-x-auto">
        {{ $slot }}
    </div>

    {{-- Pagination --}}
    @if($paginator && $paginator->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">
            {{ $paginator->links() }}
        </div>
    @endif
</div>
