@extends('layouts.app')
@section('title', 'تصحيح أسماء المنتجات')
@section('content')
<x-page-header title="تصحيح أسماء المنتجات" subtitle="اختر الاسم الصحيح لكل منتج متشابه" />

<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
    <div class="flex items-start gap-3">
        <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <h3 class="text-amber-800 font-semibold mb-1">تم اكتشاف {{ count($similarities) }} أسماء متشابهة</h3>
            <p class="text-amber-700 text-sm">يرجى مراجعة كل منتج واختيار الاسم الصحيح من القائمة أو إنشاء اسم جديد.</p>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('products.reconciliation.process') }}" id="reconciliationForm">
    @csrf
    <input type="hidden" name="company_id" value="{{ $company_id }}">
    <input type="hidden" name="upload_batch_id" value="{{ $upload_batch_id }}">

    <div class="space-y-4">
        @forelse($similarities as $originalName => $data)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="p-4 border-b border-slate-200 bg-slate-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-500 block mb-1">الاسم من الملف</span>
                            <span class="font-semibold text-slate-800 text-lg">{{ $data['original'] }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="choices[{{ $loop->index }}][create_new]" value="1" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500" onchange="toggleCreateNew(this, {{ $loop->index }})">
                                <span class="text-sm text-slate-700">إنشاء اسم جديد</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <input type="hidden" name="choices[{{ $loop->index }}][original]" value="{{ $data['original'] }}">
                    
                    <!-- Similar Products List -->
                    <div id="similar-list-{{ $loop->index }}" class="space-y-2">
                        @forelse($data['similar'] as $similar)
                            <label class="flex items-center p-3 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors similar-option" data-index="{{ $loop->parent->index }}">
                                <input type="radio" name="choices[{{ $loop->parent->index }}][selected_product_id]" value="{{ $similar['product']->id }}" class="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500" @if($loop->first) checked @endif>
                                <div class="flex-1 mr-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-slate-800">{{ $similar['product']->name }}</span>
                                        <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full">{{ round($similar['similarity'] * 100) }}% تشابه</span>
                                    </div>
                                    <div class="text-sm text-slate-500 mt-0.5">كود: {{ $similar['product']->code }}</div>
                                </div>
                            </label>
                        @empty
                            <div class="text-slate-500 text-sm py-2">لا توجد منتجات متشابهة</div>
                        @endforelse
                    </div>

                    <!-- Search for other products -->
                    <div class="mt-3 pt-3 border-t border-slate-200">
                        <div class="flex gap-2">
                            <input type="text" 
                                   placeholder="ابحث عن منتج آخر..." 
                                   class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   oninput="searchProduct(this, {{ $loop->index }}, {{ $company_id }})"
                                   data-index="{{ $loop->index }}">
                        </div>
                        <div id="search-results-{{ $loop->index }}" class="mt-2 hidden"></div>
                    </div>

                    <!-- Create New Product Input (hidden by default) -->
                    <div id="create-new-{{ $loop->index }}" class="mt-3 pt-3 border-t border-slate-200 hidden">
                        <label class="block text-sm font-medium text-slate-700 mb-1">الاسم الجديد</label>
                        <input type="text" 
                               name="choices[{{ $loop->index }}][new_name]" 
                               value="{{ $data['original'] }}"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-8 text-center">
                <div class="text-slate-500">لا توجد أسماء متشابهة للتصحيح</div>
            </div>
        @endforelse
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('imports.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors text-sm">
            إلغاء
        </a>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
            حفظ ومتابعة
        </button>
    </div>
</form>

<script>
let searchTimeouts = {};

function toggleCreateNew(checkbox, index) {
    const createNewDiv = document.getElementById(`create-new-${index}`);
    const similarList = document.getElementById(`similar-list-${index}`);
    
    if (checkbox.checked) {
        createNewDiv.classList.remove('hidden');
        similarList.classList.add('opacity-50', 'pointer-events-none');
        
        // Uncheck all radio buttons
        const radios = similarList.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => radio.checked = false);
    } else {
        createNewDiv.classList.add('hidden');
        similarList.classList.remove('opacity-50', 'pointer-events-none');
    }
}

function searchProduct(input, index, companyId) {
    const query = input.value.trim();
    const resultsDiv = document.getElementById(`search-results-${index}`);
    
    clearTimeout(searchTimeouts[index]);
    
    if (query.length < 2) {
        resultsDiv.classList.add('hidden');
        return;
    }
    
    searchTimeouts[index] = setTimeout(() => {
        fetch(`{{ route('products.reconciliation.search') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                query: query,
                company_id: companyId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.results && data.results.length > 0) {
                resultsDiv.innerHTML = data.results.map(product => `
                    <label class="flex items-center p-2 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors mt-2">
                        <input type="radio" 
                               name="choices[${index}][selected_product_id]" 
                               value="${product.id}" 
                               class="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500">
                        <div class="flex-1 mr-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-800">${product.name}</span>
                                <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">${product.similarity}% تشابه</span>
                            </div>
                            <div class="text-sm text-slate-500 mt-0.5">كود: ${product.code}</div>
                        </div>
                    </label>
                `).join('');
                resultsDiv.classList.remove('hidden');
            } else {
                resultsDiv.innerHTML = '<div class="text-slate-500 text-sm py-2">لا توجد نتائج</div>';
                resultsDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
    }, 300);
}
</script>
@endsection
