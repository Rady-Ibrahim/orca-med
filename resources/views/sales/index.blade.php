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

@if(session('status'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
        {{ session('status') }}
    </div>
@endif

<div class="space-y-6">
    {{-- Filters Form --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form action="{{ route('sales.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">المورد</label>
                <select name="supplier_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">الكل</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">المحافظة</label>
                <select name="province_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">الكل</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">من تاريخ</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">إلى تاريخ</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">بحث</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="اسم الصيدلية أو المنتج" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">سعر من</label>
                <input type="number" name="price_from" value="{{ request('price_from') }}" min="0" step="0.01" placeholder="0.00" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">سعر إلى</label>
                <input type="number" name="price_to" value="{{ request('price_to') }}" min="0" step="0.01" placeholder="0.00" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition-colors">تصفية</button>
            <a href="{{ route('sales.index') }}" class="inline-flex items-center justify-center bg-white text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50 hover:text-slate-900 py-2 px-4 text-sm font-medium transition-colors shadow-sm">إعادة تعيين</a>
        </form>
    </div>

    {{-- فورم حذف الكل المستقلة --}}
    <form id="deleteAllForm" method="POST" action="{{ route('sales.destroy-all', request()->query()) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    {{-- Bulk delete form wraps the table --}}
    <form id="bulkForm" method="POST" action="{{ route('sales.bulk-destroy') }}">
        @csrf
        @method('DELETE')

        {{-- Sales Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">

            {{-- Table header with actions --}}
            <div class="p-4 border-b border-slate-200 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <h3 class="text-lg font-semibold text-slate-800">المبيعات</h3>
                    {{-- تم تعديل text-red إلى text-white لتعديل شكل الخط داخل الزرار الأحمر --}}
                    <button type="button" id="bulkDeleteBtn"
                        onclick="confirmBulkDelete()"
                        class="inline-flex items-center gap-1.5 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                        style="background-color: #dc2626;">
                        
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>

                        حذف المحدد (<span id="selectedCount">0</span>)
                    </button>
                </div>
                
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        onclick="confirmDeleteAll(event)"
                        style="background:red;color:white;padding:8px 12px;border-radius:8px;">
                        حذف الكل
                    </button>
                    <a href="{{ route('sales.export.pdf', request()->query()) }}" target="_blank"
                        class="inline-flex items-center gap-1.5 bg-rose-600 text-white px-3 py-1.5 rounded-lg hover:bg-rose-700 transition-colors text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        PDF
                    </a>
                    <a href="{{ route('sales.export.excel', request()->query()) }}"
                        class="inline-flex items-center gap-1.5 bg-emerald-600 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Excel
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 w-10">
                                <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500" title="تحديد الكل">
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الصيدلية</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المنتج</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكمية</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">التاريخ</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">السعر</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الخصم</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الإجمالى</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المورد</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المحافظة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 w-16">حذف</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($sales as $sale)
                            @php $total = $sale->lineRevenue(); @endphp
                            <tr class="hover:bg-slate-50 row-item" data-id="{{ $sale->id }}">
                                <td class="px-3 py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $sale->id }}"
                                        class="row-checkbox w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->pharmacy?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $sale->product?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->quantity }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->sold_at?->format('Y-m-d') ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->unit_price ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->discount ?? '-' }}%</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ number_format($total, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->supplier?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $sale->province?->name ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <button type="button" onclick="confirmSingleDelete({{ $sale->id }})" class="text-red-500 hover:text-red-700 transition-colors" title="حذف">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    <form id="singleDeleteForm-{{ $sale->id }}" method="POST" action="{{ route('sales.destroy', $sale) }}" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-8 text-center text-slate-500">لا توجد مبيعات بعد</td>
                            </tr>
                        @endforelse
                    </tbody>
                    
                    @if(isset($tableTotals) && (is_array($tableTotals) ? ($tableTotals['count'] ?? 0) : ($tableTotals->count ?? 0)) > 0)
                        <tfoot class="bg-slate-100 font-semibold">
                            <tr>
                                <td class="px-3 py-3"></td>
                                <td class="px-4 py-3 text-sm text-slate-800" colspan="2">
                                    الإجمالي ({{ is_array($tableTotals['count']) ? count($tableTotals['count']) : number_format($tableTotals['count']) }} سجل)
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-800">
                                    {{ is_array($tableTotals['quantity']) ? '0' : number_format($tableTotals['quantity']) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-500">—</td>
                                <td class="px-4 py-3 text-sm text-slate-800">
                                    {{ is_array($tableTotals['gross']) ? '0.00' : number_format($tableTotals['gross'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-500">—</td>
                                <td class="px-4 py-3 text-sm text-emerald-700">
                                    {{ is_array($tableTotals['revenue']) ? '0.00' : number_format($tableTotals['revenue'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-500" colspan="3">—</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if($sales->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $sales->appends(request()->except('page'))->links() }}
                </div>
            @endif
        </div>
    </form>{{-- end bulkForm --}}
</div>

@endif
@endsection

@push('scripts')
<script>
// تأكيد جلب العناصر للتأكد من عدم وجود تضارب برامترات الكاش
document.addEventListener('DOMContentLoaded', function() {
    const selectAll      = document.getElementById('selectAll');
    const checkboxes     = document.querySelectorAll('.row-checkbox');
    const bulkDeleteBtn  = document.getElementById('bulkDeleteBtn');
    const selectedCount  = document.getElementById('selectedCount');

    function updateBulkBtn() {
        const checked = document.querySelectorAll('.row-checkbox:checked').length;
        if(selectedCount) selectedCount.textContent = checked;
        
        if (checked > 0) {
            bulkDeleteBtn.classList.remove('hidden');
            bulkDeleteBtn.classList.add('inline-flex');
        } else {
            bulkDeleteBtn.classList.add('hidden');
            bulkDeleteBtn.classList.remove('inline-flex');
        }
        if(selectAll) {
            selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
            selectAll.checked = checked === checkboxes.length && checkboxes.length > 0;
        }
    }

    if(selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkBtn();
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateBulkBtn));
});

function confirmBulkDelete() {
    const count = document.querySelectorAll('.row-checkbox:checked').length;
    if (count === 0) return;
    if (confirm(`هل تريد حذف ${count} سجل بيع محدد؟ لا يمكن التراجع عن هذا الإجراء.`)) {
        document.getElementById('bulkForm').submit();
    }
}

function confirmDeleteAll(event) {
    if (event) event.preventDefault();
    if (confirm("هل تريد حذف كل السجلات المعروضة؟ لا يمكن التراجع.")) {
        document.getElementById('deleteAllForm').submit();
    }
}

function confirmSingleDelete(id) {
    if (confirm('هل تريد حذف هذا السجل؟')) {
        document.getElementById('singleDeleteForm-' + id).submit();
    }
}
</script>
@endpush