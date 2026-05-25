@extends('layouts.app')
@section('title', 'أكواد التفعيل')
@section('content')
<x-page-header title="إدارة أكواد التفعيل" subtitle="إنشاء وإدارة أكواد تفعيل التحليلات" />

<div class="bg-white rounded-xl border border-slate-200 shadow-sm mb-6">
    <div class="p-6 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">إنشاء كود جديد</h3>
        <form method="POST" action="{{ route('activation-codes.store') }}" class="flex gap-4 items-end">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 mb-2">الشركة</label>
                <select name="company_id" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">للجميع الشركات</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-32">
                <label class="block text-sm font-medium text-slate-700 mb-2">المدة (أيام)</label>
                <input type="number" name="duration_days" value="1" min="1" required
                    class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="w-32">
                <label class="block text-sm font-medium text-slate-700 mb-2">أقصى استخدام</label>
                <input type="number" name="max_uses" value="1" min="1" required
                    class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                إنشاء
            </button>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm">
    <div class="p-6 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">الأكواد</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكود</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الشركة</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المدة (أيام)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الاستخدام</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الحالة</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($codes as $code)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono font-bold text-slate-800">{{ $code->code }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $code->company?->name ?? 'للجميع' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $code->duration_days }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $code->used_count }} / {{ $code->max_uses }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($code->isAvailable())
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    متاح
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    منتهي
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <form method="POST" action="{{ route('activation-codes.destroy', $code) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا الكود؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                            لا توجد أكواد تفعيل
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($codes->hasPages())
        <div class="p-4 border-t border-slate-200">
            {{ $codes->links() }}
        </div>
    @endif
</div>
@endsection
