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
    <x-kpi-card label="إجمالي الإيرادات" :value="$totals['total_revenue'] ?? 0" suffix="ج.م" color="emerald" icon="💰" />
    <x-kpi-card label="متوسط قيمة البيع" :value="($totals['total_revenue'] ?? 0) / max($totals['sales_count'] ?? 1, 1)" suffix="ج.م" color="indigo" icon="📊" />
</div>

{{-- Row 1: المحافظة + الزمن --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات حسب المحافظة</h3>
        <div class="relative w-full" style="height:240px">
            <canvas id="chartProvinces"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات عبر الزمن</h3>
        <div class="relative w-full" style="height:240px">
            <canvas id="chartTime"></canvas>
        </div>
    </div>
</div>

{{-- Row 2: أعلى المنتجات + أعلى الموردين --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-700">أعلى 10 منتجات مبيعاً</h3>
            <span class="text-xs bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full font-medium">الأكثر طلباً</span>
        </div>
        @php $maxSales = collect($charts['top_products'] ?? [])->max('value') ?? 1; @endphp
        <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
            @foreach(collect($charts['top_products'] ?? [])->take(10) as $i => $p)
            <div class="flex flex-col space-y-1">
                <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center rounded-md text-xs font-bold {{ $i < 3 ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $i+1 }}</span>
                        <span class="text-slate-800 font-medium truncate" title="{{ $p['label'] }}">{{ $p['label'] }}</span>
                    </div>
                    <span class="font-bold text-indigo-900 bg-slate-50 px-2 py-0.5 rounded text-xs border border-slate-100 shrink-0">{{ number_format($p['value']) }}</span>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-gradient-to-l from-indigo-500 to-blue-600 h-1.5 rounded-full" style="width:{{ ($p['value']/$maxSales)*100 }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى الموردين نشاطاً</h3>
        <div class="relative w-full" style="height:380px">
            <canvas id="chartSuppliers"></canvas>
        </div>
    </div>
</div>
</div>{{-- end content-summary --}}
@endif

{{-- Tab Content: Pharmacy Details (for activated companies) --}}
@if(auth()->user()->hasAnalyticsAccess())
<div id="content-pharmacy-details" class="tab-content hidden">
@if(isset($pharmacy_details) && $pharmacy_details->isNotEmpty())
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
                @foreach($pharmacy_details as $item)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $item->product_name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ $item->pharmacy_name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $item->province_name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $item->pharmacy_phone ?? '-' }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-green-600">{{ number_format($item->total_quantity) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ number_format($item->avg_unit_price ?? 0, 2) }} ج.م</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $item->sales_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

{{-- Pagination --}}
@if($pharmacy_details->hasPages())
    <div class="mt-4 flex justify-center">
        {{ $pharmacy_details->links() }}
    </div>
@endif

</div>{{-- end card --}}
@else
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center text-slate-500">
    لا توجد بيانات للصيدليات
</div>
@endif{{-- end isset pharmacy_details --}}
</div>{{-- end content-pharmacy-details --}}
@endif{{-- end hasAnalyticsAccess --}}

@endsection

@push('scripts')
@if(auth()->user()->hasAnalyticsAccess())
<script>
const charts = @json($charts);

Chart.defaults.font.family = 'Cairo';
Chart.defaults.color = '#64748b';

function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.classList.remove('border-blue-500', 'text-blue-600');
        el.classList.add('border-transparent', 'text-slate-500');
    });
    document.getElementById('content-' + tabName).classList.remove('hidden');
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.remove('border-transparent', 'text-slate-500');
    activeTab.classList.add('border-blue-500', 'text-blue-600');
}

function makeVerticalBar(id, labels, data, color) {
    const ctx = document.getElementById(id);
    if (!ctx || !labels.length) return;
    new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: color, hoverBackgroundColor: '#1e3a8a', borderRadius: 6, borderSkipped: false, barThickness: 24 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#f1f5f9' } } }
        }
    });
}

function makeHorizontalBar(id, labels, data, startColor, endColor) {
    const ctx = document.getElementById(id);
    if (!ctx || !labels.length) return;
    const g = ctx.getContext('2d').createLinearGradient(0, 0, ctx.offsetWidth, 0);
    g.addColorStop(0, startColor); g.addColorStop(1, endColor);
    new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: g, borderRadius: 4, borderSkipped: false, barThickness: 14 }] },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } }
        }
    });
}

function makeLine(id, labels, data) {
    const ctx = document.getElementById(id);
    if (!ctx || !labels.length) return;
    const gFill = ctx.getContext('2d').createLinearGradient(0, 0, 0, ctx.offsetHeight);
    gFill.addColorStop(0, 'rgba(99,102,241,0.15)'); gFill.addColorStop(1, 'rgba(99,102,241,0)');
    new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [{ data, borderColor: '#4f46e5', borderWidth: 2.5, backgroundColor: gFill, fill: true, tension: 0.35, pointBackgroundColor: '#4f46e5' }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#f1f5f9' } } }
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    if (charts.sales_by_province)  makeVerticalBar('chartProvinces', charts.sales_by_province.map(r=>r.label), charts.sales_by_province.map(r=>r.value), '#3b82f6');
    if (charts.sales_over_time)    makeLine('chartTime', charts.sales_over_time.map(r=>r.label), charts.sales_over_time.map(r=>r.value));
    if (charts.top_suppliers)      makeHorizontalBar('chartSuppliers', charts.top_suppliers.map(r=>r.label), charts.top_suppliers.map(r=>r.value), '#334155', '#0f172a');
});
</script>
@endif
@endpush
