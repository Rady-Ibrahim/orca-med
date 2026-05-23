@extends('layouts.app')

@section('title', 'لوحة القيادة')

@section('content')

<x-page-header title="لوحة القيادة" subtitle="ملخص بيانات الشركة" />

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-kpi-card label="المنتجات"      :value="$totals['products']      ?? 0" color="violet" icon="💊" />
    <x-kpi-card label="سجلات البيع"   :value="$totals['sales_count']   ?? 0" color="blue"   icon="📋" />
    <x-kpi-card label="إجمالي الوحدات" :value="$totals['quantity_sold'] ?? 0" color="green"  icon="📦" />
    <x-kpi-card label="المحافظات"     :value="$totals['provinces']     ?? 0" color="amber"  icon="🗺" />
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

@endsection

@push('scripts')
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
@endpush
