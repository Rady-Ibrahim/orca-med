@php
    $user = auth()->user();
    $current = request()->route()?->getName();

    $adminNav = [
        ['route' => 'dashboard',        'label' => 'لوحة القيادة',     'icon' => 'home'],
        ['route' => 'provinces.index',  'label' => 'المحافظات',        'icon' => 'map'],
        ['route' => 'suppliers.index',  'label' => 'الموردون',         'icon' => 'truck'],
        ['route' => 'pharmacies.index', 'label' => 'الصيدليات',        'icon' => 'building'],
        ['route' => 'products.index',   'label' => 'المنتجات',         'icon' => 'package'],
        ['route' => 'sales.index',      'label' => 'المبيعات',         'icon' => 'chart-bar'],
        ['route' => 'reports.index',    'label' => 'التقارير',         'icon' => 'document'],
        ['route' => 'imports.index',    'label' => 'استيراد Excel',    'icon' => 'upload'],
        ['route' => 'users.index',      'label' => 'المستخدمون',       'icon' => 'users'],
        ['route' => 'companies.index',  'label' => 'الشركات',          'icon' => 'office'],
        ['route' => 'activation-codes.index', 'label' => 'أكواد التفعيل',   'icon' => 'key'],
        ['route' => 'activation.index', 'label' => 'تفعيل التحليلات',   'icon' => 'lock'],
    ];

    $companyNav = [
        ['route' => 'dashboard',               'label' => 'لوحة القيادة',      'icon' => 'home'],
        ['route' => 'products.index',          'label' => 'المنتجات',          'icon' => 'package'],
        ['route' => 'sales.index',             'label' => 'المبيعات',          'icon' => 'chart-bar'],
        ['route' => 'reports.index',           'label' => 'التقارير',          'icon' => 'document'],
        ['route' => 'analytics.products',      'label' => 'تحليلات المبيعات', 'icon' => 'trending'],
        ['route' => 'activation.index',        'label' => 'تفعيل التحليلات',   'icon' => 'key'],
    ];

    $warehouseNav = [
        ['route' => 'dashboard', 'label' => 'لوحة القيادة', 'icon' => 'home'],
    ];

    $navItems = match(true) {
        $user?->isAdmin()     => $adminNav,
        $user?->isCompanyUser() => $companyNav,
        $user?->isWarehouseUser() => $warehouseNav,
        default     => [],
    };
@endphp

@foreach($navItems as $item)
    @php
        try {
            $url = route($item['route']);
            $isActive = $current === $item['route'];
        } catch (\Exception $e) {
            $url = '#';
            $isActive = false;
        }
    @endphp
    <a href="{{ $url }}"
       class="flex items-center gap-3 px-4 py-2.5 mx-2 rounded-lg text-sm transition-colors
              {{ $isActive
                 ? 'bg-blue-600 text-white font-semibold'
                 : 'text-blue-100 hover:bg-blue-800 hover:text-white' }}">

        {{-- Icon --}}
        @if($item['icon'] === 'home')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        @elseif($item['icon'] === 'map')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
        @elseif($item['icon'] === 'truck')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l1.5 0M13 16l1.5 0M13 16V8h5l2 8H14.5M13 6h-2"/></svg>
        @elseif($item['icon'] === 'building')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        @elseif($item['icon'] === 'package')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        @elseif($item['icon'] === 'chart-bar')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        @elseif($item['icon'] === 'document')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        @elseif($item['icon'] === 'search')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        @elseif($item['icon'] === 'upload')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        @elseif($item['icon'] === 'users')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        @elseif($item['icon'] === 'office')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        @elseif($item['icon'] === 'warehouse')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
        @elseif($item['icon'] === 'lock')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        @elseif($item['icon'] === 'trending')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        @elseif($item['icon'] === 'key')
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        @else
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/></svg>
        @endif

        <span>{{ $item['label'] }}</span>
    </a>
@endforeach
