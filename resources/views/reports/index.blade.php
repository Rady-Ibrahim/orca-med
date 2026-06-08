@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
    <x-page-header title="التقارير" subtitle="تقارير المبيعات والتحليلات المتقدمة">
        <div class="flex gap-2">
            <a href="{{ route('reports.export-products', $filters) }}" target="_blank"
                class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                PDF
            </a>
            <a href="{{ route('reports.export-products-excel', $filters) }}" target="_blank"
                class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                Excel
            </a>
        </div>
    </x-page-header>

    @if (!$has_analytics_access && auth()->user()->isCompanyUser())
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-amber-800 mb-2">تفعيل التحليلات المتقدمة</h3>
                    <p class="text-amber-700 text-sm">لعرض التقارير المالية والتفاصيل المتقدمة، يرجى تفعيل الحساب</p>
                </div>
                <a href="{{ route('activation.index') }}"
                    class="bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium">
                    تفعيل الآن
                </a>
            </div>
        </div>
    @endif

    {{-- Date Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
        <form action="{{ route('reports.index') }}" method="GET" class="flex flex-wrap gap-4 items-end">
            @if (auth()->user()->isAdmin())
                {{-- Company Filter --}}
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">الشركة</label>
                    <select name="company_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-800">
                        <option value="">الكل</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}"
                                {{ $filters['company_id'] == $company->id ? 'selected' : '' }}>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">من تاريخ</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-800">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">إلى تاريخ</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-800">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">المورد</label>
                <select name="supplier_id"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-800">
                    <option value="">الكل</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ ($filters['supplier_id'] ?? null) == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium shadow-sm h-[40px]">
                تطبيق الفلتر
            </button>
            <a href="{{ route('reports.index') }}" class="text-slate-600 hover:text-slate-800 py-2 px-4 text-sm font-medium h-[40px] flex items-center">
                إعادة تعيين
            </a>
        </form>
    </div>

    {{-- General KPIs (always visible) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-kpi-card label="المنتجات" :value="$totals['products'] ?? 0" color="violet" icon="💊" />
        <x-kpi-card label="سجلات البيع" :value="$totals['sales_count'] ?? 0" color="blue" icon="📋" />
        <x-kpi-card label="إجمالي الوحدات" :value="$totals['quantity_sold'] ?? 0" color="green" icon="📦" />
        <x-kpi-card label="المحافظات" :value="$totals['provinces'] ?? 0" color="amber" icon="🗺" />
    </div>

    @if ($has_analytics_access || auth()->user()->isAdmin())
        {{-- Financial KPIs --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <x-kpi-card label="إجمالي الإيرادات" :value="$totals['total_revenue'] ?? 0" suffix="ج.م" color="emerald" icon="💰" />
            <x-kpi-card label="إجمالي الخصومات" :value="$additional_kpis['total_discount'] ?? 0" suffix="ج.م" color="red" icon="📉" />
            <x-kpi-card label="متوسط الخصم" :value="$additional_kpis['avg_discount_percent'] ?? 0" suffix="%" color="amber" icon="📊" />
            <x-kpi-card label="عدد العمليات" :value="$totals['sales_count'] ?? 0" color="blue" icon="📋" />
        </div>

        {{-- Top Pharmacy --}}
        @if ($additional_kpis['top_pharmacy'])
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
                <h3 class="text-sm font-semibold text-slate-500 mb-3">أفضل صيدلية أداءً</h3>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xl font-bold text-slate-800">{{ $additional_kpis['top_pharmacy']['name'] }}</p>
                        <p class="text-sm text-slate-500 mt-0.5">{{ $additional_kpis['top_pharmacy']['count'] }} عملية بيع</p>
                    </div>
                    <div class="text-left">
                        <p class="text-2xl font-bold text-emerald-600">
                            {{ number_format($additional_kpis['top_pharmacy']['revenue'], 2) }} ج.م</p>
                        <p class="text-sm text-slate-400 mt-0.5">إجمالي الإيرادات</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Revenue by Product Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-base font-semibold text-slate-800">أعلى 10 منتجات بالإيرادات</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">المنتج</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">الكمية</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">الإيرادات</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">النسبة</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">متوسط السعر</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @php
                            $totalRevenue = collect($additional_kpis['revenue_per_product'])->sum('revenue');
                        @endphp
                        @forelse($additional_kpis['revenue_per_product'] as $product)
                            @php
                                $avgPrice = $product['quantity'] > 0 ? $product['revenue'] / $product['quantity'] : 0;
                                $percentage = $totalRevenue > 0 ? round(($product['revenue'] / $totalRevenue) * 100, 2) : 0;
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-3.5 text-sm font-medium text-slate-800">{{ $product['name'] }}</td>
                                <td class="px-6 py-3.5 text-sm text-slate-600">{{ number_format($product['quantity']) }}</td>
                                <td class="px-6 py-3.5 text-sm font-bold text-emerald-600">{{ number_format($product['revenue'], 2) }} ج.م</td>
                                <td class="px-6 py-3.5 text-sm text-slate-600">{{ $percentage }}%</td>
                                <td class="px-6 py-3.5 text-sm text-slate-600">{{ number_format($avgPrice, 2) }} ج.م</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-slate-400 text-sm">
                                    لا توجد بيانات متاحة حالياً
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($totalRevenue > 0)
                        <tfoot class="bg-slate-50 font-semibold border-t border-slate-200 text-slate-800">
                            <tr>
                                <td class="px-6 py-3.5 text-sm">الإجمالي</td>
                                <td class="px-6 py-3.5 text-sm">
                                    {{ number_format(collect($additional_kpis['revenue_per_product'])->sum('quantity')) }}
                                </td>
                                <td class="px-6 py-3.5 text-sm text-emerald-700">
                                    {{ number_format($totalRevenue, 2) }} ج.م
                                </td>
                                <td class="px-6 py-3.5 text-sm">100%</td>
                                <td class="px-6 py-3.5 text-sm">
                                    @php
                                        $totalQty = collect($additional_kpis['revenue_per_product'])->sum('quantity');
                                    @endphp
                                    {{ $totalQty > 0 ? number_format($totalRevenue / $totalQty, 2) : '0.00' }} ج.م
                                </td>
                            </tr>
                        </footer>
                    @endif
                </table>
            </div>
        </div>
    @endif

    {{-- Stats by Company (Admin Only) --}}
    @if (auth()->user()->isAdmin() && isset($charts['stats_by_company']))
        @php
            $totalCompanyRevenue = collect($charts['stats_by_company'])->sum('total_revenue');
            $totalCompanyQuantity = collect($charts['stats_by_company'])->sum('quantity_sold');
        @endphp
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-base font-semibold text-slate-800">إحصائيات حسب الشركة</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">الشركة</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">عدد العمليات</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">إجمالي الوحدات</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">نسبة الوحدات</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">إجمالي الإيرادات</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">نسبة الإيرادات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($charts['stats_by_company'] as $company)
                            @php
                                $quantityPercentage = $totalCompanyQuantity > 0 ? round(($company['quantity_sold'] / $totalCompanyQuantity) * 100, 2) : 0;
                                $revenuePercentage = $totalCompanyRevenue > 0 ? round(($company['total_revenue'] / $totalCompanyRevenue) * 100, 2) : 0;
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-3.5 text-sm font-medium text-slate-800">{{ $company['company_name'] }}</td>
                                <td class="px-6 py-3.5 text-sm text-slate-600">{{ number_format($company['sales_count']) }}</td>
                                <td class="px-6 py-3.5 text-sm text-slate-600">{{ number_format($company['quantity_sold']) }}</td>
                                <td class="px-6 py-3.5 text-sm text-slate-600">{{ $quantityPercentage }}%</td>
                                <td class="px-6 py-3.5 text-sm font-bold text-emerald-600">{{ number_format($company['total_revenue'], 2) }} ج.م</td>
                                <td class="px-6 py-3.5 text-sm text-slate-600">{{ $revenuePercentage }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-slate-400 text-sm">
                                    لا توجد بيانات متاحة حالياً
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($totalCompanyRevenue > 0)
                        <tfoot class="bg-slate-50 font-semibold border-t border-slate-200 text-slate-800">
                            <tr>
                                <td class="px-6 py-3.5 text-sm">الإجمالي</td>
                                <td class="px-6 py-3.5 text-sm">
                                    {{ number_format(collect($charts['stats_by_company'])->sum('sales_count')) }}
                                </td>
                                <td class="px-6 py-3.5 text-sm">{{ number_format($totalCompanyQuantity) }}</td>
                                <td class="px-6 py-3.5 text-sm">100%</td>
                                <td class="px-6 py-3.5 text-sm text-emerald-700">
                                    {{ number_format($totalCompanyRevenue, 2) }} ج.م
                                </td>
                                <td class="px-6 py-3.5 text-sm">100%</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

    {{-- Charts & Analytics Row --}}
    @if ($has_analytics_access || auth()->user()->isAdmin())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            {{-- القائمة الذكية المحدثة لأعلى 10 منتجات مبيعاً لإظهار الأرقام بوضوح --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-sm font-semibold text-slate-700">أعلى المنتجات مبيعاً</h3>
                        <span class="text-xs bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full font-medium">الأكثر طلباً</span>
                    </div>
                    
                    @php
                        $maxSales = collect($charts['top_products'] ?? [])->max('value') ?? 1;
                    @endphp

                    <div class="space-y-3.5 max-h-[320px] overflow-y-auto pr-1">
                        @foreach(collect($charts['top_products'] ?? [])->take(10) as $index => $product)
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
                                    <span class="font-bold text-indigo-900 bg-slate-50 px-2 py-0.5 rounded text-xs border border-slate-100">
                                        {{ number_format($product['value']) }}
                                    </span>
                                </div>
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
                <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى الموردين ({{ count($charts['top_suppliers'] ?? []) }})</h3>
                <div class="relative w-full" style="height: 360px;">
                    <canvas id="chartSuppliers"></canvas>
                </div>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    {{-- نفتح الشرط داخل الـ push --}}
    @if ($has_analytics_access || auth()->user()->isAdmin())
        <script>
            // التأكد من أن البيانات موجودة قبل تمريرها لـ JavaScript
            const charts = @json($charts ?? []);

            // إعدادات الخطوط الافتراضية
            if (typeof Chart !== 'undefined') {
                Chart.defaults.font.family = 'Cairo';
                Chart.defaults.color = '#64748b';
            }

            // 1. دالة الشارت العمودي
            function makeVerticalBar(id, labels, data, baseColor) {
                const ctx = document.getElementById(id);
                if (!ctx || !labels.length) return;
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            data,
                            backgroundColor: baseColor,
                            hoverBackgroundColor: '#1e3a8a',
                            borderRadius: 6,
                            barThickness: 24
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' } }
                        }
                    }
                });
            }

            // 2. دالة الشارت الأفقي
            function makeHorizontalBar(id, labels, data, startColor, endColor) {
                const ctx = document.getElementById(id);
                if (!ctx || !labels.length) return;
                
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
                            barThickness: 14
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            // 3. دالة شارت خط الزمن
            function makeLine(id, labels, data) {
                const ctx = document.getElementById(id);
                if (!ctx || !labels.length) return;
                
                const chartContext = ctx.getContext('2d');
                const gradientFill = chartContext.createLinearGradient(0, 0, 0, ctx.offsetHeight);
                gradientFill.addColorStop(0, 'rgba(37, 99, 235, 0.15)');
                gradientFill.addColorStop(1, 'rgba(37, 99, 235, 0.00)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            data,
                            borderColor: '#2563eb',
                            borderWidth: 2.5,
                            backgroundColor: gradientFill,
                            fill: true,
                            tension: 0.35
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true }
                        }
                    }
                });
            }

            // تنفيذ الرسوم عند تحميل الصفحة
            document.addEventListener('DOMContentLoaded', function() {
                if (charts.sales_by_province) {
                    makeVerticalBar('chartProvinces', charts.sales_by_province.map(r => r.label), charts.sales_by_province.map(r => r.value), '#3b82f6');
                }
                if (charts.sales_over_time) {
                    makeLine('chartTime', charts.sales_over_time.map(r => r.label), charts.sales_over_time.map(r => r.value));
                }
                if (charts.top_suppliers) {
                    makeHorizontalBar('chartSuppliers', charts.top_suppliers.map(r => r.label), charts.top_suppliers.map(r => r.value), '#334155', '#0f172a');
                }
            });
        </script>
    @endif
@endpush {{-- لاحظ أننا نغلق الـ push هنا --}}