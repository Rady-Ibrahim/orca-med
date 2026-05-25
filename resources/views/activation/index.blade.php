@extends('layouts.app')
@section('title', 'تفعيل التحليلات')
@section('content')
<x-page-header title="تفعيل التحليلات" subtitle="أدخل كود التفعيل لفتح التحليلات المتقدمة" />

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-8 max-w-md mx-auto">
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('activation.activate') }}">
        @csrf
        <div class="mb-4">
            <label for="code" class="block text-sm font-medium text-slate-700 mb-2">كود التفعيل</label>
            <input type="text" id="code" name="code" required
                class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="أدخل كود التفعيل">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
            تفعيل
        </button>
    </form>

    <div class="mt-6 text-center text-sm text-slate-500">
        <p>تواصل مع الإدارة للحصول على كود التفعيل</p>
    </div>
</div>
@endsection
