@extends('layouts.app')
@section('title', 'تحليل المحافظات')
@section('content')
@if(!auth()->user()->hasAnalyticsAccess())
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-10 text-center">
        <div class="text-slate-400 mb-4">يجب تفعيل كود الوصول للإحصائيات أولاً</div>
        <a href="{{ route('activation.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            تفعيل كود الوصول
        </a>
    </div>
@else
    <x-page-header title="تحليل المبيعات حسب المحافظة" />
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-10 text-center text-slate-400">قريباً — تحليل المحافظات</div>
@endif
@endsection
