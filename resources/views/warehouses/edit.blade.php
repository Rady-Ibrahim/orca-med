@extends('layouts.app')
@section('title', 'تعديل مخزن')
@section('content')
<x-page-header title="تعديل: {{ $warehouse->name }}">
    <a href="{{ route('warehouses.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← العودة</a>
</x-page-header>
<div class="max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" action="{{ route('warehouses.update', $warehouse) }}" class="space-y-4">
            @csrf @method('PUT')
            @include('warehouses._form', ['warehouse' => $warehouse])
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm px-5 py-2 rounded-lg transition-colors">حفظ التعديلات</button>
                <a href="{{ route('warehouses.index') }}" class="text-slate-600 text-sm px-5 py-2 rounded-lg border border-slate-300 hover:bg-slate-50 transition-colors">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
