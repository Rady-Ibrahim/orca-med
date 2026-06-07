@extends('layouts.app')
@section('title', 'المنتجات')
@section('content')
    <x-page-header title="إدارة المنتجات" subtitle="قائمة المنتجات المسجلة" />

    @if (auth()->user()->isCompanyUser() && !auth()->user()->hasAnalyticsAccess())
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-amber-800 mb-2">تفعيل التحليلات</h3>
                    <p class="text-amber-700 text-sm">يجب تفعيل الحساب لعرض قائمة المنتجات</p>
                </div>
                <a href="{{ route('activation.index') }}"
                    class="bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium">
                    تفعيل الآن
                </a>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-6 border-b border-slate-200">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">المنتجات</h3>
                        <p class="text-sm text-slate-500">البحث عن طريق اسم المنتج أو الكود.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <form method="GET" action="{{ route('products.index') }}"
                            class="flex flex-col sm:flex-row gap-3 items-stretch">
                            <input name="search" type="text" value="{{ request('search') }}"
                                placeholder="ابحث باسم المنتج أو الكود..."
                                class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-full sm:w-72 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @if (auth()->user()->isAdmin() && $companies->isNotEmpty())
                                <select name="company_id"
                                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                                    <option value="">كل الشركات</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}"
                                            {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                بحث
                            </button>
                        </form>

                        <a href="{{ route('products.create') }}"
                            class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors text-sm">
                            إضافة منتج جديد
                        </a>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكود</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الاسم</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">السعر</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الشركة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($products as $product)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $product->code }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    {{ $product->effectivePrice() !== null ? number_format($product->effectivePrice(), 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $product->company?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('products.edit', $product) }}"
                                        class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                                        تعديل
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                    لا توجد منتجات بعد
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $products->links() }}
                </div>
            @endif
        </div>

    @endif
@endsection
