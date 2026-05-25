@extends('layouts.app')

@section('title', 'تفاصيل الدفعة')

@section('content')
<x-page-header title="تفاصيل الدفعة">
    <a href="{{ route('imports.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← العودة</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Batch Info --}}
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">معلومات الدفعة</h3>

            <dl class="space-y-3">
                <div>
                    <dt class="text-sm text-slate-500">الملف</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $batch->original_filename }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">الشركة</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $batch->company?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">المورد</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $batch->supplier?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">المحافظة</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $batch->province?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">تم الرفع بواسطة</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $batch->uploader?->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">تاريخ الرفع</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $batch->created_at->format('Y-m-d H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">الحالة</dt>
                    <dd>
                        @php
                            $statusClass = match($batch->status->value) {
                                'queued' => 'bg-yellow-100 text-yellow-800',
                                'processing' => 'bg-blue-100 text-blue-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'partial' => 'bg-orange-100 text-orange-800',
                                'failed' => 'bg-red-100 text-red-800',
                                default => 'bg-slate-100 text-slate-800',
                            };
                            $statusLabel = match($batch->status->value) {
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
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">إجمالي الصفوف</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $batch->total_rows ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">ناجح</dt>
                    <dd class="text-sm font-medium text-green-600">{{ $batch->success_count ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">أخطاء</dt>
                    <dd class="text-sm font-medium text-red-600">{{ $batch->error_count ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">مكرر</dt>
                    <dd class="text-sm font-medium text-orange-600">{{ $batch->duplicate_count ?? 0 }}</dd>
                </div>
            </dl>

            {{-- Download Error Report --}}
            @if($batch->error_report_path && $batch->error_count > 0)
                <div class="mt-4 pt-4 border-t border-slate-200">
                    <a href="{{ route('imports.download-errors', $batch) }}" class="block text-center text-sm text-blue-600 hover:text-blue-700">
                        تحميل تقرير الأخطاء (CSV)
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Errors Table --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-800">الأخطاء</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الصف</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">العمود</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">نوع الخطأ</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الرسالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($batch->errors as $error)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $error->row_number }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $error->column ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $errorTypeClass = match($error->error_type) {
                                            'validation' => 'bg-yellow-100 text-yellow-800',
                                            'not_found' => 'bg-red-100 text-red-800',
                                            'duplicate' => 'bg-orange-100 text-orange-800',
                                            default => 'bg-slate-100 text-slate-800',
                                        };
                                        $errorTypeLabel = match($error->error_type) {
                                            'validation' => 'تحقق',
                                            'not_found' => 'غير موجود',
                                            'duplicate' => 'مكرر',
                                            default => $error->error_type,
                                        };
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $errorTypeClass }}">
                                        {{ $errorTypeLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $error->message }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                    لا توجد أخطاء
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
