@extends('layouts.app')

@section('title', 'لوحة القيادة')

@section('content')

<x-page-header title="لوحة القيادة" subtitle="نظرة عامة على النظام" />

{{-- KPI Grid --}}
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <x-kpi-card label="المحافظات"   :value="$totals['provinces']  ?? 0" color="blue"   icon="🗺" />
    <x-kpi-card label="الموردون"    :value="$totals['suppliers']  ?? 0" color="amber"  icon="🚚" />
    <x-kpi-card label="الصيدليات"  :value="$totals['pharmacies'] ?? 0" color="green"  icon="🏥" />
    <x-kpi-card label="المنتجات"   :value="$totals['products']   ?? 0" color="violet" icon="💊" />
    <x-kpi-card label="الشركات"    :value="\App\Models\Company::count()" color="indigo" icon="🏢" />
    <x-kpi-card label="سجلات البيع" :value="$totals['sales_count']    ?? 0" color="slate"  icon="📊" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <x-kpi-card label="إجمالي الإيرادات" :value="$totals['total_revenue'] ?? 0" suffix="ج.م" color="emerald" icon="💰" />
    <x-kpi-card label="متوسط قيمة البيع" :value="($totals['total_revenue'] ?? 0) / max($totals['sales_count'] ?? 1, 1)" suffix="ج.م" color="indigo" icon="📈" />
</div>

{{-- Charts Rows --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    {{-- المبيعات حسب المحافظة --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات حسب المحافظة</h3>
        <div class="relative w-full" style="height: 240px;">
            <canvas id="chartProvinces"></canvas>
        </div>
    </div>
    
    {{-- المبيعات عبر الزمن --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات عبر الزمن</h3>
        <div class="relative w-full" style="height: 240px;">
            <canvas id="chartTime"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    {{-- قائمة أعلى 10 منتجات مبيعاً بالأرقام الواضحة --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">أعلى 10 منتجات مبيعاً</h3>
                <span class="text-xs bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full font-medium">الأكثر طلباً</span>
            </div>
            
            @php
                // جلب أعلى قيمة مبيعات لضبط النسبة المئوية للـ Progress Bars ديناميكياً
                $maxSales = collect($charts['top_products'])->max('value') ?? 1;
            @endphp

            <div class="space-y-3.5 max-h-[340px] overflow-y-auto pr-1">
                @foreach(collect($charts['top_products'])->take(10) as $index => $product)
                    <div class="flex flex-col space-y-1">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center space-x-2 space-x-reverse min-w-0">
                                <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center rounded-md text-xs font-bold {{ $index < 3 ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $index + 1 }}
                                </span>
                                <span class="text-slate-800 font-medium truncate" title="{{ $product['label'] }}">
                                    {{ $product['label'] }}
                                </span>
                            </div>
                            <!-- الرقم ظاهر هنا بوضوح تام وبلون مميز -->
                            <span class="font-bold text-indigo-900 bg-slate-50 px-2 py-0.5 rounded text-xs border border-slate-100">
                                {{ number_format($product['value']) }}
                            </span>
                        </div>
                        {{-- شريط بصري ناعم يعكس حجم المبيعات --}}
                        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-gradient-to-l from-indigo-500 to-blue-600 h-1.5 rounded-full" style="width: {{ ($product['value'] / $maxSales) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- أعلى الموردين --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى الموردين نشاطاً</h3>
        <div class="relative w-full" style="height: 380px;">
            <canvas id="chartSuppliers"></canvas>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const charts = @json($charts);

// إعدادات الخطوط الافتراضية
Chart.defaults.font.family = 'Cairo';
Chart.defaults.color = '#64748b';

// 1. شارت المحافظات (عمودي)
function makeVerticalBar(id, labels, data, baseColor) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ 
                data, 
                backgroundColor: baseColor, 
                hoverBackgroundColor: '#1e3a8a',
                borderRadius: 6, 
                borderSkipped: false,
                barThickness: 24
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: { padding: 10, cornerRadius: 8 }
            },
            scales: { 
                x: { grid: { display: false }, ticks: { font: { size: 11 } } }, 
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } } 
            }
        }
    });
}

// 2. شارت الموردين (أفقي)
function makeHorizontalBar(id, labels, data, startColor, endColor) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    
    const chartContext = ctx.getContext('2d');
    const gradient = chartContext.createLinearGradient(0, 0, ctx.offsetWidth, 0);
    gradient.addColorStop(0, startColor);
    gradient.addColorStop(1, endColor);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ 
                data, 
                backgroundColor: gradient, 
                hoverBackgroundColor: endColor,
                borderRadius: 4, 
                borderSkipped: false,
                barThickness: 14
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: { padding: 10, cornerRadius: 8 }
            },
            scales: { 
                x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } }, 
                y: { grid: { display: false }, ticks: { font: { size: 12, weight: '500' }, color: '#334155' } } 
            }
        }
    });
}

// 3. شارت الخط الزمني
function makeLine(id, labels, data) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    
    const chartContext = ctx.getContext('2d');
    const gradientFill = chartContext.createLinearGradient(0, 0, 0, ctx.offsetHeight);
    gradientFill.addColorStop(0, 'rgba(99, 102, 241, 0.15)');
    gradientFill.addColorStop(1, 'rgba(99, 102, 241, 0.00)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{ 
                data, 
                borderColor: '#4f46e5', 
                borderWidth: 2.5,
                backgroundColor: gradientFill, 
                fill: true, 
                tension: 0.35,
                pointBackgroundColor: '#4f46e5'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: { padding: 10, cornerRadius: 8 }
            },
            scales: { 
                x: { grid: { display: false }, ticks: { font: { size: 11 } } }, 
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } } 
            }
        }
    });
}

// تشغيل الشارتات المتبقية
makeVerticalBar('chartProvinces', charts.sales_by_province.map(r => r.label), charts.sales_by_province.map(r => r.value), '#3b82f6');
makeLine('chartTime', charts.sales_over_time.map(r => r.label), charts.sales_over_time.map(r => r.value));
makeHorizontalBar('chartSuppliers', charts.top_suppliers.map(r => r.label), charts.top_suppliers.map(r => r.value), '#334155', '#0f172a');
</script>
@endpush