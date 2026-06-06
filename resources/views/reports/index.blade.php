@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
    <x-page-header title="التقارير" subtitle="تقارير المبيعات والتحليلات المتقدمة">
        <div class="flex gap-2">

            <a href="{{ route('reports.export-products', $filters) }}" target="_blank"
                class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                PDF
            </a>
            <a href="{{ route('reports.export-products-excel', $filters) }}" target="_blank"
                class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
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
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">إلى تاريخ</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">المورد</label>
                <select name="supplier_id"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">الكل</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ ($filters['supplier_id'] ?? null) == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition-colors">
                تطبيق الفلتر
            </button>
            <a href="{{ route('reports.index') }}" class="text-slate-600 hover:text-slate-800 py-2 px-4">
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
            <x-kpi-card label="إجمالي الإيرادات" :value="number_format($totals['total_revenue'] ?? 0, 2) . ' ج.م'" color="emerald" icon="💰" />
            <x-kpi-card label="إجمالي الخصومات" :value="number_format($additional_kpis['total_discount'] ?? 0, 2) . ' ج.م'" color="red" icon="📉" />
            <x-kpi-card label="متوسط الخصم" :value="number_format($additional_kpis['avg_discount_percent'] ?? 0, 2) . '%'" color="amber" icon="📊" />
            <x-kpi-card label="عدد العمليات" :value="$totals['sales_count'] ?? 0" color="blue" icon="📋" />
        </div>

        {{-- Top Pharmacy --}}
        @if ($additional_kpis['top_pharmacy'])
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">أفضل صيدلية</h3>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xl font-bold text-slate-800">{{ $additional_kpis['top_pharmacy']['name'] }}</p>
                        <p class="text-sm text-slate-500">{{ $additional_kpis['top_pharmacy']['count'] }} عملية بيع</p>
                    </div>
                    <div class="text-left">
                        <p class="text-2xl font-bold text-emerald-600">
                            {{ number_format($additional_kpis['top_pharmacy']['revenue'], 2) }} ج.م</p>
                        <p class="text-sm text-slate-500">إجمالي الإيرادات</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Revenue by Product Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-800">أعلى 10 منتجات بالإيرادات</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المنتج</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكمية</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الإيرادات</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">النسبة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">متوسط السعر</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @php
                            $totalRevenue = collect($additional_kpis['revenue_per_product'])->sum('revenue');
                        @endphp
                        @forelse($additional_kpis['revenue_per_product'] as $product)
                            @php
                                $avgPrice = $product['quantity'] > 0 ? $product['revenue'] / $product['quantity'] : 0;
                                $percentage =
                                    $totalRevenue > 0 ? round(($product['revenue'] / $totalRevenue) * 100, 2) : 0;
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $product['name'] }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $product['quantity'] }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-emerald-600">
                                    {{ number_format($product['revenue'], 2) }} ج.م</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $percentage }}%</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ number_format($avgPrice, 2) }} ج.م</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                    لا توجد بيانات
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
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
                <h3 class="text-lg font-semibold text-slate-800">إحصائيات حسب الشركة</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الشركة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">عدد العمليات</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">إجمالي الوحدات</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">نسبة الوحدات</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">إجمالي الإيرادات</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">نسبة الإيرادات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($charts['stats_by_company'] as $company)
                            @php
                                $quantityPercentage =
                                    $totalCompanyQuantity > 0
                                        ? round(($company['quantity_sold'] / $totalCompanyQuantity) * 100, 2)
                                        : 0;
                                $revenuePercentage =
                                    $totalCompanyRevenue > 0
                                        ? round(($company['total_revenue'] / $totalCompanyRevenue) * 100, 2)
                                        : 0;
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $company['company_name'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $company['sales_count'] }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $company['quantity_sold'] }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $quantityPercentage }}%</td>
                                <td class="px-4 py-3 text-sm font-bold text-emerald-600">
                                    {{ number_format($company['total_revenue'], 2) }} ج.م</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $revenuePercentage }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                                    لا توجد بيانات
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Charts Row --}}
    @if ($has_analytics_access || auth()->user()->isAdmin())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات حسب المحافظة</h3>
                <canvas id="chartProvinces" height="220"></canvas>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-4">المبيعات عبر الزمن</h3>
                <canvas id="chartTime" height="220"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى المنتجات مبيعاً
                    ({{ count($charts['top_products'] ?? []) }})</h3>
                <canvas id="chartTopProducts" height="220"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-4">أعلى الموردين
                    ({{ count($charts['top_suppliers'] ?? []) }})</h3>
                <canvas id="chartSuppliers" height="220"></canvas>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    @if ($has_analytics_access || auth()->user()->isAdmin())
        <script>
            const charts = @json($charts);

            function makeBar(id, labels, data, color) {
                const ctx = document.getElementById(id);
                if (!ctx) return;
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            data,
                            backgroundColor: color,
                            borderRadius: 4,
                            borderSkipped: false
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    font: {
                                        family: 'Cairo'
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true
                            }
                        }
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
                        datasets: [{
                            data,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37,99,235,0.08)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    font: {
                                        family: 'Cairo'
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true
                            }
                        }
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
    @endif
@endpush
