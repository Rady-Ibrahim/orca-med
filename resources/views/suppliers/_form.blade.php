<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">الاسم <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $supplier?->name) }}" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">المحافظة <span class="text-red-500">*</span></label>
    <select name="province_id" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('province_id') border-red-400 @enderror">
        <option value="">اختر المحافظة</option>
        @foreach($provinces as $p)
            <option value="{{ $p->id }}" {{ old('province_id', $supplier?->province_id) == $p->id ? 'selected' : '' }}>
                {{ $p->name }}
            </option>
        @endforeach
    </select>
    @error('province_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">الهاتف</label>
    <input type="text" name="phone" value="{{ old('phone', $supplier?->phone) }}" dir="ltr"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">العنوان</label>
    <textarea name="address" rows="2"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('address', $supplier?->address) }}</textarea>
</div>
