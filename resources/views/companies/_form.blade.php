<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">اسم الشركة <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $company?->name) }}" required
        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-slate-300' }}">
    @error('name')
        <p class="text-red-600 text-xs mt-1 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            {{ $message }}
        </p>
    @enderror
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
        <input type="email" name="contact_email" value="{{ old('contact_email', $company?->contact_email) }}"
               required dir="ltr"
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
