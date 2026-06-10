<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">الاسم <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $user?->name) }}" required
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-400 @enderror">
        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
        <input type="email" name="email" value="{{ old('email', $user?->email) }}" required dir="ltr"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-400 @enderror">
        @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">
        كلمة المرور
        @if($user) <span class="text-slate-400 font-normal text-xs">(اتركها فارغة إذا لم تغيّرها)</span> @else <span class="text-red-500">*</span> @endif
    </label>
    <input type="password" name="password" autocomplete="new-password" {{ $user ? '' : 'required' }}
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-400 @enderror">
    @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">الدور <span class="text-red-500">*</span></label>
    <select name="role" required id="role-select"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('role') border-red-400 @enderror">
        <option value="">اختر الدور</option>
        @foreach($roles as $r)
            @if($r->value === 'warehouse') @continue @endif
            <option value="{{ $r->value }}" {{ old('role', $user?->role?->value) === $r->value ? 'selected' : '' }}>
                @if($r->value === 'admin') أدمن
                @elseif($r->value === 'company') شركة
                @endif
            </option>
        @endforeach
    </select>
    @error('role')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

<div id="field-company" class="{{ old('role', $user?->role?->value) === 'company' ? '' : 'hidden' }}">
    <label class="block text-sm font-medium text-slate-700 mb-1">الشركة <span class="text-red-500">*</span></label>
    <select name="company_id" id="company-select"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">اختر الشركة</option>
        @foreach($companies as $c)
            <option value="{{ $c->id }}" {{ old('company_id', $user?->company_id) == $c->id ? 'selected' : '' }}>
                {{ $c->name }}
            </option>
        @endforeach
    </select>
</div>

@push('scripts')
<script>
const roleSelect   = document.getElementById('role-select');
const fieldCompany = document.getElementById('field-company');
const companySelect = document.getElementById('company-select');

function updateRoleFields() {
    const v = roleSelect.value;
    const showCompany = v === 'company';
    fieldCompany.classList.toggle('hidden', !showCompany);
    // Make company_id required only when role = company
    companySelect.required = showCompany;
}
roleSelect.addEventListener('change', updateRoleFields);
// Run on page load to set correct required state
updateRoleFields();
</script>
@endpush
