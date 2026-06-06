@extends('layouts.app')

@section('title', 'استيراد مبيعات Excel')

@section('content')
    <x-page-header title="استيراد مبيعات Excel" subtitle="رفع ملفات Excel لاستيراد بيانات المبيعات" />

    <div class="space-y-6">
        {{-- Upload Form --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">رفع ملف جديد</h3>

            <form action="{{ route('imports.store') }}" method="POST" enctype="multipart/form-data"
                class="flex flex-wrap gap-4 items-end">
                @csrf

                {{-- Company Select --}}
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">الشركة</label>
                    <select name="company_id" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">اختر الشركة...</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Supplier Select --}}
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">المورد</label>
                    <select name="supplier_id" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">اختر المورد...</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Province Select --}}
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">المحافظة</label>
                    <select name="province_id" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">اختر المحافظة...</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province->id }}">{{ $province->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- File Upload --}}
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-700 mb-1">ملف Excel</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                {{-- Submit Button --}}
                <button type="submit"
                    class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition-colors">
                    رفع وبدء المعالجة
                </button>

            </form>
        </div>

        {{-- Batches Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-800">سجل الرفوعات</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الملف</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الشركة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المورد</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">المحافظة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">النتيجة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">التاريخ</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($batches as $batch)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $batch->original_filename }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $batch->company?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $batch->supplier?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $batch->province?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusClass = match ($batch->status->value) {
                                            'queued' => 'bg-yellow-100 text-yellow-800',
                                            'processing' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'partial' => 'bg-orange-100 text-orange-800',
                                            'failed' => 'bg-red-100 text-red-800',
                                            default => 'bg-slate-100 text-slate-800',
                                        };
                                        $statusLabel = match ($batch->status->value) {
                                            'queued' => 'في الانتظار',
                                            'processing' => 'جاري المعالجة',
                                            'completed' => 'مكتمل',
                                            'partial' => 'جزئي',
                                            'failed' => 'فشل',
                                            default => $batch->status->value,
                                        };
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    @if ($batch->status->value === 'completed' || $batch->status->value === 'partial')
                                        <span class="text-green-600">{{ $batch->success_count }} ناجح</span>
                                        @if ($batch->error_count > 0)
                                            <span class="text-red-600">، {{ $batch->error_count }} خطأ</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $batch->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('imports.show', $batch) }}"
                                            class="text-blue-600 hover:text-blue-700 text-sm">
                                            عرض التفاصيل
                                        </a>
                                        <form action="{{ route('imports.destroy', $batch) }}" method="POST"
                                            onsubmit="return confirm('هل أنت متأكد من حذف هذه الرفعة؟ سيتم حذف جميع البيانات المرتبطة بها من قاعدة البيانات.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-700 text-sm">
                                                حذف
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                    لا توجد رفعات بعد
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($batches->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $batches->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
