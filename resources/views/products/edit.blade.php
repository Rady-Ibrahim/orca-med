@extends('layouts.app')
@section('title', 'تعديل منتج')
@section('content')
    <x-page-header title="تعديل منتج"><a href="{{ route('products.index') }}"
            class="text-sm text-slate-500 hover:text-slate-700">← العودة</a></x-page-header>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 max-w-3xl mx-auto">
        <form method="POST" action="{{ route('products.update', $product) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">الاسم</label>
                    <input name="name" type="text" value="{{ old('name', $product->name) }}"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">الكود</label>
                    <input name="code" type="text" value="{{ old('code', $product->code) }}"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('code')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">السعر</label>
                    <input name="price" type="number" step="0.01" value="{{ old('price', $product->price) }}"
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('price')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if ($companies->isNotEmpty())
                    <div>
                        <label class="block text-sm font-medium text-slate-700">الشركة</label>
                        <select name="company_id"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}"
                                    {{ old('company_id', $product->company_id) == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('products.index') }}"
                    class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 transition-colors">إلغاء</a>
                <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">تحديث
                    المنتج</button>
            </div>
        </form>
    </div>
@endsection
