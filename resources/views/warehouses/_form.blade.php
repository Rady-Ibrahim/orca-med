<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">اسم المخزن <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $warehouse?->name) }}" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">النوع <span class="text-red-500">*</span></label>
    <select name="type" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('type') border-red-400 @enderror">
        <option value="">اختر النوع</option>
        @foreach($types as $t)
            <option value="{{ $t->value }}" {{ old('type', $warehouse?->type?->value) === $t->value ? 'selected' : '' }}>
                {{ $t->label() }}
            </option>
        @endforeach
    </select>
    @error('type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">المحافظة</label>
    <select name="province_id"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">اختر المحافظة (اختياري)</option>
        @foreach($provinces as $p)
            <option value="{{ $p->id }}" {{ old('province_id', $warehouse?->province_id) == $p->id ? 'selected' : '' }}>
                {{ $p->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">الهاتف</label>
        <input type="text" name="phone" value="{{ old('phone', $warehouse?->phone) }}" dir="ltr"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">العنوان</label>
        <input type="text" name="address" value="{{ old('address', $warehouse?->address) }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
</div>
