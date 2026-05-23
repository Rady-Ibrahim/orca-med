@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-slate-100 px-4" style="font-family:'Cairo',sans-serif">
    <div class="w-full max-w-sm">
        <div class="bg-white rounded-2xl shadow-lg p-8">

            {{-- Logo --}}
            <div class="flex items-center gap-3 mb-7">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center font-bold text-white text-lg shrink-0">O</div>
                <div>
                    <div class="font-bold text-slate-800 text-lg leading-tight">Orca Med</div>
                    <div class="text-xs text-slate-500">توزيع ومتابعة الأدوية</div>
                </div>
            </div>

            <h2 class="text-base font-semibold text-slate-700 mb-5">تسجيل الدخول</h2>

            {{-- Errors --}}
            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email', 'admin@orca-med.test') }}"
                        required autocomplete="username"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">كلمة المرور</label>
                    <input type="password" name="password" value="password"
                        required autocomplete="current-password"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg py-2.5 text-sm transition-colors">
                    دخول
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
