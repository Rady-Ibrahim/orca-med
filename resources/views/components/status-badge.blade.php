@props([
    'status' => 'active',
])

@php
    $map = [
        'active'    => ['bg' => 'bg-green-100',  'text' => 'text-green-700',  'label' => 'نشط'],
        'inactive'  => ['bg' => 'bg-slate-100',  'text' => 'text-slate-600',  'label' => 'غير نشط'],
        'pending'   => ['bg' => 'bg-amber-100',  'text' => 'text-amber-700',  'label' => 'معلق'],
        'approved'  => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'label' => 'مقبول'],
        'rejected'  => ['bg' => 'bg-red-100',    'text' => 'text-red-700',    'label' => 'مرفوض'],
        'processing'=> ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'label' => 'جاري المعالجة'],
        'done'      => ['bg' => 'bg-green-100',  'text' => 'text-green-700',  'label' => 'مكتمل'],
        'failed'    => ['bg' => 'bg-red-100',    'text' => 'text-red-700',    'label' => 'فشل'],
        'admin'     => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'label' => 'أدمن'],
        'company'   => ['bg' => 'bg-violet-100', 'text' => 'text-violet-700', 'label' => 'شركة'],
        'warehouse' => ['bg' => 'bg-amber-100',  'text' => 'text-amber-700',  'label' => 'مخزن'],
    ];
    $s = $map[$status] ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'label' => $status];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $s['bg'] }} {{ $s['text'] }}">
    {{ $slot->isNotEmpty() ? $slot : $s['label'] }}
</span>
