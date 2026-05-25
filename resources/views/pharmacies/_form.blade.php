<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">اسم الصيدلية <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $pharmacy?->name) }}" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">المورد <span class="text-red-500">*</span></label>
    <select name="supplier_id" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('supplier_id') border-red-400 @enderror">
        <option value="">اختر المورد</option>
        @foreach($suppliers as $s)
            <option value="{{ $s->id }}" {{ old('supplier_id', $pharmacy?->supplier_id) == $s->id ? 'selected' : '' }}>
                {{ $s->name }} @if($s->province)({{ $s->province->name }})@endif
            </option>
        @endforeach
    </select>
    @error('supplier_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    <p class="text-xs text-slate-400 mt-1">المحافظة ستُحدَّد تلقائياً من المورد إذا تُركت فارغة</p>
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">المحافظة (اختياري — يُكمل تلقائياً)</label>
    <select name="province_id"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">تلقائي من المورد</option>
        @foreach($provinces as $p)
            <option value="{{ $p->id }}" {{ old('province_id', $pharmacy?->province_id) == $p->id ? 'selected' : '' }}>
                {{ $p->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">رقم الترخيص</label>
        <input type="text" name="license_number" value="{{ old('license_number', $pharmacy?->license_number) }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">الهاتف</label>
        <input type="text" name="phone" value="{{ old('phone', $pharmacy?->phone) }}" dir="ltr"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">العنوان</label>
    <textarea name="address" rows="2"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('address', $pharmacy?->address) }}</textarea>
</div>
