@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@push('head')
<style>
    body.orca-ui {
        background: white !important;
    }
    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        overflow-x: hidden;
    }
</style>
@endpush

@section('content')
<div style="display: flex; width: 100%; min-height: 100vh; font-family: 'Cairo', sans-serif; background-color: #ffffff; margin: 0; padding: 0;">

    {{-- الجانب الأيمن - فورم تسجيل الدخول --}}
    <div style="width: 50%; display: flex; align-items: center; justify-content: center; padding: 3rem; background-color: #ffffff;">
        <div style="width: 100%; max-width: 400px; display: flex; flex-direction: column; gap: 1.5rem;">

            <div style="text-align: right;">
                <h2 style="font-size: 1.75rem; font-weight: 700; color: #1e293b; margin: 0 0 0.5rem 0;">تسجيل الدخول</h2>
                <p style="font-size: 0.875rem; color: #64748b; margin: 0;">مرحباً بك مجدداً، يرجى إدخال بياناتك للمتابعة.</p>
            </div>

            {{-- الأخطاء --}}
            @if($errors->any())
                <div style="border-radius: 0.75rem; background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;" dir="rtl">
                    <span style="width: 6px; height: 6px; background-color: #dc2626; border-radius: 50%; flex-shrink: 0;"></span>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" style="display: flex; flex-direction: column; gap: 1.25rem;" dir="rtl">
                @csrf

                <div style="display: flex; flex-direction: column; gap: 0.5rem; text-align: right;">
                    <label style="font-size: 0.875rem; font-weight: 600; color: #334155; margin: 0;">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email', 'admin@orca-med.test') }}"
                        required autocomplete="username"
                        style="width: 100%; border-radius: 0.75rem; border: 1px solid #cbd5e1; padding: 0.75rem 1rem; font-size: 0.875rem; color: #1e293b; outline: none; background-color: rgba(248, 250, 252, 0.5); transition: all 0.2s;"
                        onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.15)'"
                        onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none'">
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem; text-align: right;">
                    <label style="font-size: 0.875rem; font-weight: 600; color: #334155; margin: 0;">كلمة المرور</label>
                    <input type="password" name="password" value="password"
                        required autocomplete="current-password"
                        style="width: 100%; border-radius: 0.75rem; border: 1px solid #cbd5e1; padding: 0.75rem 1rem; font-size: 0.875rem; color: #1e293b; outline: none; background-color: rgba(248, 250, 252, 0.5); transition: all 0.2s;"
                        onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.15)'"
                        onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none'">
                </div>

                <button type="submit"
                    style="width: 100%; background-color: #2563eb; color: #ffffff; font-weight: 700; border-radius: 0.75rem; padding: 0.875rem; font-size: 0.875rem; border: none; cursor: pointer; transition: all 0.2s; margin-top: 0.5rem;"
                    onmouseover="this.style.backgroundColor='#1d4ed8'"
                    onmouseout="this.style.backgroundColor='#2563eb'">
                    دخول
                </button>
            </form>
        </div>
    </div>

    {{-- الجانب الأيسر - الصورة --}}
    <div style="width: 50%; display: flex; align-items: center; justify-content: center; background-color: #ffffff; padding: 0;">
        <img src="/images/orca-med-.jpeg" alt="Orca Med" style="max-width: 100%; max-height: 100vh; width: auto; height: auto; object-fit: contain;">
    </div>

</div>
@endsection