@extends('layouts.app')

@section('title', 'تفاصيل الصيدلية')

@section('content')
<x-page-header title="تفاصيل الصيدلية" subtitle="معلومات الصيدلية وسجل مبيعاتها" />

<div class="space-y-6">
    {{-- Pharmacy Info Card --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-xl font-semibold text-slate-800 mb-4">
                    @if($maskSensitiveData)
                        <span class="text-slate-400">*** صيدلية مشفرة ***</span>
                    @else
                        {{ $pharmacy->name }}
                    @endif
                </h3>
                
                <dl class="space-y-3">
                    @if(!$maskSensitiveData)
                        <div class="flex items-center gap-3">
                            <dt class="text-sm font-medium text-slate-500 w-24">رقم الترخيص:</dt>
                            <dd class="text-sm text-slate-700">{{ $pharmacy->license_number ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center gap-3">
                            <dt class="text-sm font-medium text-slate-500 w-24">الهاتف:</dt>
                            <dd class="text-sm text-slate-700">{{ $pharmacy->phone ?? '-' }}</dd>
                        </div>
                        <div class="flex items-center gap-3">
                            <dt class="text-sm font-medium text-slate-500 w-24">العنوان:</dt>
                            <dd class="text-sm text-slate-700">{{ $pharmacy->address ?? '-' }}</dd>
                        </div>
                    @endif
                    
                    <div class="flex items-center gap-3">
                        <dt class="text-sm font-medium text-slate-500 w-24">المورد:</dt>
                        <dd class="text-sm text-slate-700">{{ $pharmacy->supplier?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex items-center gap-3">
                        <dt class="text-sm font-medium text-slate-500 w-24">المحافظة:</dt>
                        <dd class="text-sm text-slate-700">{{ $pharmacy->province?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex items-center gap-3">
                        <dt class="text-sm font-medium text-slate-500 w-24">المخزن:</dt>
                        <dd class="text-sm text-slate-700">{{ $pharmacy->warehouse?->name ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
            
            @if($maskSensitiveData)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 max-w-xs">
                    <p class="text-sm text-amber-800">
                        <strong>ملاحظة:</strong> بيانات الصيدلية مشفرة لأنك لا تملك صلاحية الوصول لتفاصيل هذا الصنف.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Sales Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-kpi-card title="إجمالى المبيعات" :value="$pharmacy->sales->count() . ' عملية'" icon="shopping-cart" />
        <x-kpi-card title="إجمالى العبوات" :value="$pharmacy->sales->sum('quantity') . ' عبوة'" icon="package" />
        <x-kpi-card title="القيمة المالية" :value="number_format($pharmacy->sales->sum(fn($s) => $s->quantity * $s->unit_price * (1 - $s->discount / 100)), 2) . ' ج.م'" icon="currency" />
    </div>

    {{-- Sales Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="p-6 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">سجل المبيعات</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المنتج</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكمية</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">السعر</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الخصم</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الإجمالى</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($pharmacy->sales as $sale)
                        @php
                            $total = $sale->quantity * $sale->unit_price * (1 - $sale->discount / 100);
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $sale->product?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->unit_price ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->discount ?? '-' }}%</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ number_format($total, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->sold_at?->format('Y-m-d') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                                لا توجد مبيعات لهذه الصيدلية
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Back Button --}}
    <div>
        <a href="{{ route('pharmacies.index') }}" class="inline-flex items-center text-slate-600 hover:text-slate-800">
            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            العودة للصيدليات
        </a>
    </div>
</div>
@endsection
