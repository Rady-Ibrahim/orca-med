@extends('layouts.app')

@section('title', 'لوحة القيادة')

@section('content')

<x-page-header title="لوحة القيادة" subtitle="نظرة عامة على النظام" />

{{-- KPI Grid --}}
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <x-kpi-card label="المحافظات"  :value="$totals['provinces']  ?? 0" color="blue"   icon="🗺" />
    <x-kpi-card label="الموردون"   :value="$totals['suppliers']  ?? 0" color="amber"  icon="🚚" />
    <x-kpi-card label="الصيدليات"  :value="$totals['pharmacies'] ?? 0" color="green"  icon="🏥" />
    <x-kpi-card label="المنتجات"   :value="$totals['products']   ?? 0" color="violet" icon="💊" />
    <x-kpi-card label="الشركات"    :value="\App\Models\Company::count()" color="indigo" icon="🏢" />
    <x-kpi-card label="سجلات البيع" :value="$totals['sales_count']    ?? 0" color="slate"  icon="�" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <x-kpi-card label="إجمالي الإيرادات" :value="number_format($totals['total_revenue'] ?? 0, 2) . ' ج.م'" color="emerald" icon="💰" />
    <x-kpi-card label="متوسط قيمة البيع" :value="number_format(($totals['total_revenue'] ?? 0) / max($totals['sales_count'] ?? 1, 1), 2) . ' ج.م'" color="indigo" icon="📊" />
</div>

{{-- Charts Row --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات حسب المحافظة</h3>
        <canvas id="chartProvinces" height="220"></canvas>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات عبر الزمن</h3>
        <canvas id="chartTime" height="220"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى 10 منتجات</h3>
        <canvas id="chartTopProducts" height="220"></canvas>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى الموردين</h3>
        <canvas id="chartSuppliers" height="220"></canvas>
    </div>
</div>

@endsection

@push('scripts')
<script>
const charts = @json($charts);

function makeBar(id, labels, data, color) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ data, backgroundColor: color, borderRadius: 4, borderSkipped: false }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { ticks: { font: { family: 'Cairo' } } }, y: { beginAtZero: true } }
        }
    });
}

function makeLine(id, labels, data) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{ data, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.08)', fill: true, tension: 0.4 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { ticks: { font: { family: 'Cairo' } } }, y: { beginAtZero: true } }
        }
    });
}

makeBar('chartProvinces',
    charts.sales_by_province.map(r => r.label),
    charts.sales_by_province.map(r => r.value),
    '#3b82f6');

makeLine('chartTime',
    charts.sales_over_time.map(r => r.label),
    charts.sales_over_time.map(r => r.value));

makeBar('chartTopProducts',
    charts.top_products.map(r => r.label),
    charts.top_products.map(r => r.value),
    '#8b5cf6');

makeBar('chartSuppliers',
    charts.top_suppliers.map(r => r.label),
    charts.top_suppliers.map(r => r.value),
    '#f59e0b');
</script>
@endpush
