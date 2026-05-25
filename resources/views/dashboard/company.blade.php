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

@if(auth()->user()->hasAnalyticsAccess())
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <x-kpi-card label="إجمالي الإيرادات" :value="number_format($totals['total_revenue'] ?? 0, 2) . ' ج.م'" color="emerald" icon="💰" />
    <x-kpi-card label="متوسط قيمة البيع" :value="number_format(($totals['total_revenue'] ?? 0) / max($totals['sales_count'] ?? 1, 1), 2) . ' ج.م'" color="indigo" icon="📊" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات حسب المحافظة</h3>
        <canvas id="chartProvinces" height="220"></canvas>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى المنتجات مبيعاً</h3>
        <canvas id="chartTopProducts" height="220"></canvas>
    </div>
</div>
@endif

@endsection

@push('scripts')
@if(auth()->user()->hasAnalyticsAccess())
<script>
const charts = @json($charts);
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
