@extends('layouts.app')
@section('title', 'المستخدمون')

@section('content')
<x-page-header title="إدارة المستخدمين" subtitle="حسابات الدخول وأدوارهم">
    <a href="{{ route('users.create') }}"
       class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        إضافة مستخدم
    </a>
</x-page-header>

<x-data-table :paginator="$items">
    <x-slot name="filters">
        <form method="GET" class="flex items-center gap-3 flex-wrap">
            <select name="role"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">كل الأدوار</option>
                @foreach($roles as $r)
                    <option value="{{ $r->value }}" {{ request('role') === $r->value ? 'selected' : '' }}>
                        @if($r->value === 'admin') أدمن @elseif($r->value === 'company') شركة @else مخزن @endif
                    </option>
                @endforeach
            </select>
            <select name="company_id"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">كل الشركات</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" {{ request('company_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-blue-700 transition-colors">بحث</button>
            @if(request()->anyFilled(['role','company_id']))
                <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:text-slate-700">مسح</a>
            @endif
        </form>
    </x-slot>

    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">الاسم</th>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">البريد</th>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">الدور</th>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">الشركة / المخزن</th>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">الإجراءات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($items as $user)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $user->name }}</td>
                    <td class="px-5 py-3 text-slate-500 text-xs" dir="ltr">{{ $user->email }}</td>
                    <td class="px-5 py-3"><x-status-badge :status="$user->role?->value ?? 'inactive'" /></td>
                    <td class="px-5 py-3 text-slate-600">
                        {{ $user->company?->name ?? $user->warehouse?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('users.edit', $user) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">تعديل</a>
                            @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $user) }}"
                                      onsubmit="return confirm('حذف المستخدم «{{ $user->name }}»؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">حذف</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">لا يوجد مستخدمون</td></tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>
@endsection
