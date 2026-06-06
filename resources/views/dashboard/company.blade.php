@extends('layouts.app')

@section('title', 'لوحة القيادة')

@section('content')

<x-page-header title="لوحة القيادة" subtitle="ملخص بيانات الشركة" />

@if(!auth()->user()->hasAnalyticsAccess())
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-amber-800 mb-2">تفعيل التحليلات المتقدمة</h3>
                <p class="text-amber-700 text-sm">لعرض الإيرادات المالية والتفاصيل المتقدمة، يرجى تفعيل الحساب</p>
            </div>
            <a href="{{ route('activation.index') }}" class="bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium">
                تفعيل الآن
            </a>
        </div>
    </div>
@endif

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-kpi-card label="المنتجات"      :value="$totals['products']      ?? 0" color="violet" icon="💊" />
    <x-kpi-card label="سجلات البيع"   :value="$totals['sales_count']   ?? 0" color="blue"   icon="📋" />
    <x-kpi-card label="إجمالي الوحدات" :value="$totals['quantity_sold'] ?? 0" color="green"  icon="📦" />
    <x-kpi-card label="المحافظات"     :value="$totals['provinces']     ?? 0" color="amber"  icon="🗺" />
</div>

{{-- Tabs Navigation (for activated companies) --}}
@if(auth()->user()->hasAnalyticsAccess())
<div class="mb-6">
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-8 space-x-reverse" aria-label="Tabs">
            <button onclick="switchTab('summary')" id="tab-summary" class="tab-btn border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                الملخص
            </button>
            <button onclick="switchTab('pharmacy-details')" id="tab-pharmacy-details" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                تفاصيل الصيدليات
            </button>
        </nav>
    </div>
</div>

{{-- Tab Content: Summary --}}
<div id="content-summary" class="tab-content">
@endif

{{-- Quantity Summaries Table (for non-activated companies) --}}
@if(!auth()->user()->hasAnalyticsAccess() && isset($quantity_summaries) && $quantity_summaries['type'] === 'totals_only')
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-slate-800">ملخص الكميات</h3>
        <div class="text-sm text-slate-600">
            عدد المنتجات: {{ $quantity_summaries['overall']['total_products'] }} |
            إجمالي الكميات: {{ number_format($quantity_summaries['overall']['total_quantity']) }}
        </div>
    </div>

    @if(!empty($quantity_summaries['by_product']))
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المنتج</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المورد</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الصيدليات</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">إجمالي الكمية</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">التفاصيل حسب المحافظة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach($quantity_summaries['by_product'] as $product)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $product['product_name'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $product['supplier_name'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $product['pharmacy_count'] ?? 0 }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-green-600">{{ number_format($product['total_quantity']) }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach($product['by_province'] as $province)
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-50 text-blue-700 rounded">
                                {{ $province['province_name'] }}: {{ number_format($province['quantity']) }}
                            </span>
                            @endforeach
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center py-8 text-slate-500">
        لا توجد بيانات للعرض
    </div>
    @endif
</div>
@endif

@if(auth()->user()->hasAnalyticsAccess())
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <x-kpi-card label="إجمالي الإيرادات" :value="number_format($totals['total_revenue'] ?? 0, 2) . ' ج.م'" color="emerald" icon="💰" />
    <x-kpi-card label="متوسط قيمة البيع" :value="number_format(($totals['total_revenue'] ?? 0) / max($totals['sales_count'] ?? 1, 1), 2) . ' ج.م'" color="indigo" icon="📊" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات حسب المحافظة</h3>
        <canvas id="chartProvinces" height="220"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى المنتجات مبيعاً</h3>
        <canvas id="chartTopProducts" height="220"></canvas>
    </div>
</div>
</div>
@endif

{{-- Tab Content: Pharmacy Details (for activated companies) --}}
@if(auth()->user()->hasAnalyticsAccess())
<div id="content-pharmacy-details" class="tab-content hidden">
@if(isset($pharmacy_details) && !empty($pharmacy_details['data']))
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">تفاصيل المبيعات حسب الصيدليات</h3>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المنتج</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الصيدلية</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المحافظة</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الهاتف</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكمية</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">متوسط السعر</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">عدد العمليات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach($pharmacy_details['data'] as $item)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $item['product_name'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $item['pharmacy_name'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $item['province_name'] }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $item['pharmacy_phone'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-green-600">{{ number_format($item['total_quantity']) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ number_format($item['avg_unit_price'] ?? 0, 2) }} ج.م</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $item['sales_count'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(isset($pharmacy_details['links']))
    <div class="mt-4 flex justify-center">
        {!! $pharmacy_details['links'] !!}
    </div>
    @endif
</div>
@else
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center text-slate-500">
    لا توجد بيانات للعرض
</div>
@endif
</div>
@endif

@endsection

@push('scripts')
@if(auth()->user()->hasAnalyticsAccess())
<script>
const charts = @json($charts);

function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.add('hidden');
    });

    // Remove active state from all tabs
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.classList.remove('border-blue-500', 'text-blue-600');
        el.classList.add('border-transparent', 'text-slate-500');
    });

    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');

    // Add active state to selected tab
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.remove('border-transparent', 'text-slate-500');
    activeTab.classList.add('border-blue-500', 'text-blue-600');
}

function makeBar(id, labels, data, color) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: color, borderRadius: 4, borderSkipped: false }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { font: { family: 'Cairo' } } }, y: { beginAtZero: true } } }
    });
}
makeBar('chartProvinces', charts.sales_by_province.map(r=>r.label), charts.sales_by_province.map(r=>r.value), '#3b82f6');
makeBar('chartTopProducts', charts.top_products.map(r=>r.label), charts.top_products.map(r=>r.value), '#8b5cf6');
</script>
@endif
@endpush
