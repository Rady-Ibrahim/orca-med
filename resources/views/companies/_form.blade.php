<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">اسم الشركة <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $company?->name) }}" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">البريد الإلكتروني</label>
        <input type="email" name="contact_email" value="{{ old('contact_email', $company?->contact_email) }}" dir="ltr"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('contact_email') border-red-400 @enderror">
        @error('contact_email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">الهاتف</label>
        <input type="text" name="contact_phone" value="{{ old('contact_phone', $company?->contact_phone) }}" dir="ltr"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
</div>

<div class="flex items-center gap-3">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" id="is_active" value="1"
        {{ old('is_active', $company?->is_active ?? true) ? 'checked' : '' }}
        class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
    <label for="is_active" class="text-sm font-medium text-slate-700">الشركة نشطة</label>
</div>
