@extends('layouts.app')
@section('title', 'المنتجات')
@section('content')
    <x-page-header title="إدارة المنتجات" subtitle="قائمة المنتجات المسجلة" />

    @if (auth()->user()->isCompanyUser() && !auth()->user()->hasAnalyticsAccess())
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-amber-800 mb-2">تفعيل التحليلات</h3>
                    <p class="text-amber-700 text-sm">يجب تفعيل الحساب لعرض قائمة المنتجات</p>
                </div>
                <a href="{{ route('activation.index') }}"
                    class="bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors text-sm font-medium">
                    تفعيل الآن
                </a>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-6 border-b border-slate-200">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">المنتجات</h3>
                        <p class="text-sm text-slate-500">البحث عن طريق اسم المنتج أو الكود.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <form method="GET" action="{{ route('products.index') }}"
                            class="flex flex-col sm:flex-row gap-3 items-stretch">
                            <input name="search" type="text" value="{{ request('search') }}"
                                placeholder="ابحث باسم المنتج أو الكود..."
                                class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-full sm:w-72 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @if (auth()->user()->isAdmin() && $companies->isNotEmpty())
                                <select name="company_id"
                                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                                    <option value="">كل الشركات</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}"
                                            {{ request('company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
                                بحث
                            </button>
                        </form>

                        <a href="{{ route('products.create') }}"
                            class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors text-sm">
                            إضافة منتج جديد
                        </a>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الكود</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الاسم</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">السعر</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">الشركة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($products as $product)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $product->code }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    {{ $product->effectivePrice() !== null ? number_format($product->effectivePrice(), 2) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $product->company?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('products.edit', $product) }}"
                                            class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                                            تعديل
                                        </a>
                                        <button type="button"
                                            onclick="openMergeModal({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->company_id }})"
                                            class="text-amber-600 hover:text-amber-700 text-sm font-medium">
                                            دمج
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                    لا توجد منتجات بعد
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="p-4 border-t border-slate-200">
                    {{ $products->links() }}
                </div>
            @endif
        </div>

    @endif
@endsection

@push('scripts')
<div id="mergeModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-1">دمج منتج</h2>
        <p class="text-sm text-slate-500 mb-4">
            سيتم نقل كل المبيعات من
            <strong id="mergeSourceName" class="text-slate-700"></strong>
            إلى المنتج الذي تختاره، ثم حذف المنتج المكرر نهائياً.
        </p>

        <form id="mergeForm" method="POST" action="">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    ادمج في المنتج الأصلي
                </label>
                <input type="text" id="mergeSearch" placeholder="ابحث باسم المنتج..."
                    autocomplete="off"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-2"
                    oninput="searchForMerge(this.value)">
                <div id="mergeResults" class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg hidden"></div>
                <input type="hidden" name="canonical_product_id" id="canonicalProductId">
                <div id="selectedCanonical" class="mt-2 text-sm text-emerald-700 font-medium hidden"></div>
            </div>

            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeMergeModal()"
                    class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm">
                    إلغاء
                </button>
                <button type="submit" id="mergeSubmit" disabled
                    class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                    تأكيد الدمج
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let mergeSearchTimeout = null;
let mergeSourceCompanyId = null;

function openMergeModal(productId, productName, companyId) {
    mergeSourceCompanyId = companyId;
    document.getElementById('mergeSourceName').textContent = productName;
    document.getElementById('mergeForm').action = '/products/' + productId + '/merge';
    document.getElementById('mergeForm').dataset.sourceId = productId;
    document.getElementById('mergeSearch').value = '';
    document.getElementById('mergeResults').innerHTML = '';
    document.getElementById('mergeResults').classList.add('hidden');
    document.getElementById('canonicalProductId').value = '';
    document.getElementById('selectedCanonical').classList.add('hidden');
    document.getElementById('mergeSubmit').disabled = true;
    document.getElementById('mergeModal').classList.remove('hidden');
    document.getElementById('mergeModal').classList.add('flex');
    setTimeout(() => document.getElementById('mergeSearch').focus(), 50);
}

function closeMergeModal() {
    document.getElementById('mergeModal').classList.add('hidden');
    document.getElementById('mergeModal').classList.remove('flex');
}

function searchForMerge(query) {
    clearTimeout(mergeSearchTimeout);
    const resultsDiv = document.getElementById('mergeResults');

    if (query.trim().length < 1) {
        resultsDiv.classList.add('hidden');
        return;
    }

    mergeSearchTimeout = setTimeout(() => {
        const sourceId = parseInt(document.getElementById('mergeForm').dataset.sourceId);

        fetch('{{ route("products.merge-search") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                query: query.trim(),
                company_id: mergeSourceCompanyId,
                exclude_id: sourceId
            })
        })
        .then(r => r.json())
        .then(data => {
            const results = data.results || [];

            if (results.length === 0) {
                resultsDiv.innerHTML = '<div class="px-3 py-2 text-sm text-slate-500">لا توجد نتائج</div>';
            } else {
                resultsDiv.innerHTML = results.map(p => {
                    const safeName = p.name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                    return `<button type="button"
                        onclick="selectCanonical(${p.id}, '${safeName}')"
                        class="w-full text-right px-3 py-2 text-sm hover:bg-blue-50 border-b border-slate-100 last:border-0 flex items-center justify-between gap-2">
                        <span class="font-medium text-slate-800">${p.name}</span>
                        <span class="text-xs text-slate-400 shrink-0">${p.price}</span>
                    </button>`;
                }).join('');
            }
            resultsDiv.classList.remove('hidden');
        })
        .catch(err => {
            resultsDiv.innerHTML = '<div class="px-3 py-2 text-sm text-red-500">خطأ في البحث</div>';
            resultsDiv.classList.remove('hidden');
        });
    }, 250);
}

function selectCanonical(id, name) {
    document.getElementById('canonicalProductId').value = id;
    document.getElementById('mergeSearch').value = name;
    document.getElementById('mergeResults').classList.add('hidden');
    document.getElementById('selectedCanonical').textContent = '✓ سيتم الدمج في: ' + name;
    document.getElementById('selectedCanonical').classList.remove('hidden');
    document.getElementById('mergeSubmit').disabled = false;
}

document.getElementById('mergeModal').addEventListener('click', function(e) {
    if (e.target === this) closeMergeModal();
});
</script>
@endpush
