@extends('layouts.app')
@section('title', 'المبيعات')
@section('content')
<x-page-header title="إدارة المبيعات" subtitle="سجلات المبيعات من الشيتات المرفوعة" />

@if(auth()->user()->isCompanyUser() && !auth()->user()->hasAnalyticsAccess())
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-amber-800 mb-2">تفعيل التحليلات</h3>
                <p class="text-amber-700 text-sm">يجب تفعيل الحساب لعرض تفاصيل المبيعات</p>
            </div>
            <a href="{{ route('activation.index') }}" class="bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium">
                تفعيل الآن
            </a>
        </div>
    </div>
@endif

@if(! (auth()->user()->isCompanyUser() && !auth()->user()->hasAnalyticsAccess()))

<div class="space-y-6">
    {{-- Filters Form --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form action="{{ route('sales.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
            {{-- Supplier Filter --}}
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">المورد</label>
                <select name="supplier_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">الكل</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Province Filter --}}
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">المحافظة</label>
                <select name="province_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">الكل</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>
                            {{ $province->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date From Filter --}}
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">من تاريخ</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Date To Filter --}}
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">إلى تاريخ</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Search Filter --}}
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">بحث</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="اسم الصيدلية أو المنتج"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Submit Button --}}
            <button type="submit" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition-colors">
                تصفية
            </button>

            {{-- Reset Button --}}
            <a href="{{ route('sales.index') }}" class="text-slate-600 hover:text-slate-800 py-2 px-4">
                إعادة تعيين
            </a>
        </form>
    </div>

    {{-- Sales Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="p-6 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">المبيعات</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الصيدلية</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المنتج</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكمية</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">التاريخ</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">السعر</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الخصم</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الإجمالى</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المورد</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المحافظة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($sales as $sale)
                        @php
                            $total = $sale->lineRevenue();
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->pharmacy?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $sale->product?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->sold_at?->format('Y-m-d') ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->unit_price ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->discount ?? '-' }}%</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ number_format($total, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->supplier?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->province?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-500">
                                لا توجد مبيعات بعد
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(($tableTotals['count'] ?? 0) > 0)
                    <tfoot class="bg-slate-100 font-semibold">
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-800" colspan="2">
                                الإجمالي ({{ number_format($tableTotals['count']) }} سجل)
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-800">{{ number_format($tableTotals['quantity']) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">—</td>
                            <td class="px-4 py-3 text-sm text-slate-800">{{ number_format($tableTotals['gross'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">—</td>
                            <td class="px-4 py-3 text-sm text-emerald-700">{{ number_format($tableTotals['revenue'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500" colspan="2">—</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if($sales->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $sales->links() }}
            </div>
        @endif
    </div>
</div>

@endif
@endsection
