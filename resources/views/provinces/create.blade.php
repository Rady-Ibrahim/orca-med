@extends('layouts.app')
@section('title', 'إضافة محافظة')

@section('content')

<x-page-header title="إضافة محافظة جديدة">
    <a href="{{ route('provinces.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← العودة للقائمة</a>
</x-page-header>

<div class="max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" action="{{ route('provinces.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">اسم المحافظة <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm px-5 py-2 rounded-lg transition-colors">
                    حفظ
                </button>
                <a href="{{ route('provinces.index') }}"
                    class="text-slate-600 hover:text-slate-800 text-sm px-5 py-2 rounded-lg border border-slate-300 transition-colors">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
