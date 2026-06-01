@extends('layouts.app')
@section('title', 'المنتجات')
@section('content')
<x-page-header title="إدارة المنتجات" subtitle="قائمة المنتجات المسجلة" />

@if(auth()->user()->isCompanyUser() && !auth()->user()->hasAnalyticsAccess())
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-amber-800 mb-2">تفعيل التحليلات</h3>
                <p class="text-amber-700 text-sm">يجب تفعيل الحساب لعرض قائمة المنتجات</p>
            </div>
            <a href="{{ route('activation.index') }}" class="bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium">
                تفعيل الآن
            </a>
        </div>
    </div>
@else

<div class="bg-white rounded-xl border border-slate-200 shadow-sm">
    <div class="p-6 border-b border-slate-200 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-slate-800">المنتجات</h3>
        <a href="{{ route('products.create') }}" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors text-sm">
            إضافة منتج جديد
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكود</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الاسم</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الشركة</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الوصف</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($products as $product)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $product->code }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $product->name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $product->company?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $product->description ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('products.edit', $product) }}" class="text-blue-600 hover:text-blue-700 text-sm mr-2">
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

    @if($products->hasPages())
        <div class="p-4 border-t border-slate-200">
            {{ $products->links() }}
        </div>
    @endif
</div>

@endif
@endsection
