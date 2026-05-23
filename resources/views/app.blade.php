@extends('layouts.guest')

@section('title', 'لوحة التحكم')

@section('content')
{{-- Mobile top bar --}}
<header class="orca-mobile-header">
    <a href="/app" class="orca-logo-mark">
        <span class="orca-logo-icon">O</span>
        <span class="orca-logo-text">Orca Med</span>
    </a>
    <span id="user-badge-mobile" style="font-size:.75rem;color:var(--muted);max-width:38%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
    <details class="orca-menu-toggle relative">
        <summary>القائمة</summary>
        <nav class="orca-menu-panel" id="main-nav-mobile"></nav>
    </details>
</header>

<div class="orca-shell">
    <aside class="orca-sidebar">
        <div class="orca-sidebar-brand">
            <a href="/app" class="orca-logo-mark">
                <span class="orca-logo-icon">O</span>
                <span class="orca-logo-text">Orca Med</span>
            </a>
        </div>
        <nav class="orca-nav" id="main-nav"></nav>
        <button type="button" class="orca-sidebar-logout" id="btn-logout">تسجيل الخروج</button>
    </aside>

    <div class="orca-main">
        <header class="orca-desktop-header">
            <div>
                <h2 id="orca-heading">لوحة التحكم</h2>
                <p class="orca-sub" id="orca-subheading">نظرة عامة على المؤشرات والرسوم</p>
            </div>
            <div class="orca-user-pill">
                <span id="user-badge" style="color:#e2e8f0"></span>
            </div>
        </header>

        <div class="orca-mobile-title">
            <h2 id="orca-mobile-heading">لوحة التحكم</h2>
            <p class="orca-sub" id="orca-mobile-sub">نظرة عامة</p>
        </div>

        <main class="orca-content">
            <div id="page-dashboard" class="orca-page"></div>
            <div id="page-provinces" class="orca-page orca-hidden"></div>
            <div id="page-suppliers" class="orca-page orca-hidden"></div>
            <div id="page-pharmacies" class="orca-page orca-hidden"></div>
            <div id="page-products" class="orca-page orca-hidden"></div>
            <div id="page-sales" class="orca-page orca-hidden"></div>
            <div id="page-reports" class="orca-page orca-hidden"></div>
            <div id="page-search" class="orca-page orca-hidden"></div>
            <div id="page-company-analytics" class="orca-page orca-hidden company-only orca-hidden"></div>
            <div id="page-import" class="orca-page orca-hidden upload-only orca-hidden"></div>
            <div id="page-access-requests" class="orca-page orca-hidden admin-only"></div>
            <div id="page-companies" class="orca-page orca-hidden admin-only"></div>
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/orca-api.js') }}"></script>
<script>
(function () {
    const navItems = [
        { page: 'dashboard', label: 'الرئيسية', heading: 'لوحة التحكم', sub: 'نظرة عامة على المؤشرات والرسوم' },
        { page: 'provinces', label: 'المحافظات', heading: 'المحافظات', sub: 'إدارة المحافظات والعدّ' },
        { page: 'suppliers', label: 'الموردون', heading: 'الموردون', sub: 'موردون حسب المحافظة' },
        { page: 'pharmacies', label: 'الصيدليات', heading: 'الصيدليات', sub: 'قائمة الصيدليات والربط' },
        { page: 'products', label: 'الأصناف', heading: 'الأصناف', sub: 'منتجات الشركات' },
        { page: 'sales', label: 'المبيعات', heading: 'المبيعات', sub: 'سجلات البيع والكميات' },
        { page: 'reports', label: 'التقارير', heading: 'التقارير', sub: 'أكثر و أقل مبيعاً' },
        { page: 'search', label: 'بحث', heading: 'بحث', sub: 'أصناف، صيدليات، موردون' },
        { page: 'company-analytics', label: 'تحليلات المبيعات', heading: 'تحليلات الشركة', sub: 'إجمالي الوحدات والمحافظات والصيدليات', company: true },
        { page: 'import', label: 'رفع Excel', heading: 'رفع ملفات', sub: 'استيراد مبيعات من Excel', upload: true },
        { page: 'access-requests', label: 'طلبات الوصول', heading: 'طلبات الوصول', sub: 'موافقة رؤية البيانات الحساسة', admin: true },
        { page: 'companies', label: 'الشركات', heading: 'الشركات', sub: 'إدارة الشركات المنتجة', admin: true },
    ];
    function renderNav(container) {
        container.innerHTML = navItems.map((item) => {
            const classes = [
                item.admin ? 'admin-only orca-hidden' : '',
                item.company ? 'company-only orca-hidden' : '',
                item.upload ? 'upload-only orca-hidden' : '',
            ].filter(Boolean).join(' ');
            return `<button type="button" data-page="${item.page}" data-heading="${item.heading}" data-sub="${item.sub}" class="${classes}">${item.label}</button>`;
        }).join('');
    }
    renderNav(document.getElementById('main-nav'));
    renderNav(document.getElementById('main-nav-mobile'));
})();
</script>
<script src="{{ asset('js/orca-app.js') }}"></script>
@endpush
