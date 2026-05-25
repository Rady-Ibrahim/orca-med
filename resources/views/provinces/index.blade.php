@extends('layouts.app')
@section('title', 'المحافظات')

@section('content')

<x-page-header title="إدارة المحافظات" subtitle="قائمة المحافظات المسجلة في النظام">
    <a href="{{ route('provinces.create') }}"
       class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        إضافة محافظة
    </a>
</x-page-header>

<x-data-table :paginator="$items">
    <x-slot name="filters">
        <form method="GET" class="flex items-center gap-3 flex-wrap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم..."
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-56">
            <button type="submit"
                class="bg-blue-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-blue-700 transition-colors">بحث</button>
            @if(request('search'))
                <a href="{{ route('provinces.index') }}" class="text-sm text-slate-500 hover:text-slate-700">مسح</a>
            @endif
        </form>
    </x-slot>

    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">#</th>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">الاسم</th>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">الموردون</th>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">الصيدليات</th>
                <th class="text-right px-5 py-3 font-semibold text-slate-600">الإجراءات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($items as $province)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3 text-slate-400">{{ $province->id }}</td>
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $province->name }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $province->suppliers_count ?? 0 }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $province->pharmacies_count ?? 0 }}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('provinces.edit', $province) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium">تعديل</a>
                            <form method="POST" action="{{ route('provinces.destroy', $province) }}"
                                  onsubmit="return confirm('حذف المحافظة «{{ $province->name }}»؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-10 text-center text-slate-400">لا توجد محافظات</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-data-table>

@endsection
