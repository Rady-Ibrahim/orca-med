{{-- Orca Med UI — inspired by Dwaa admin (sidebar + dark shell), accent: teal (not burgundy) --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --brand: #0d9488;
        --brand-hover: #14b8a6;
        --brand-muted: rgba(13, 148, 136, 0.18);
        --brand-glow: rgba(20, 184, 166, 0.35);
        --surface: #0a0f0e;
        --surface-raised: #0f1614;
        --surface-card: #141c1a;
        --border-subtle: rgba(255, 255, 255, 0.06);
        --text: #e7eceb;
        --muted: #8b9d98;
        --danger: #f43f5e;
        --danger-muted: rgba(244, 63, 94, 0.12);
    }

    *, *::before, *::after { box-sizing: border-box; }

    body.orca-ui {
        margin: 0;
        min-height: 100dvh;
        font-family: 'Tajawal', system-ui, sans-serif;
        background: var(--surface);
        color: var(--text);
        -webkit-font-smoothing: antialiased;
    }

    .orca-login-wrap {
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background:
            radial-gradient(ellipse 80% 50% at 50% -20%, rgba(13, 148, 136, 0.22), transparent),
            var(--surface);
    }

    .orca-login-card {
        width: 100%;
        max-width: 420px;
        padding: 2rem;
        border-radius: 1.25rem;
        border: 1px solid var(--border-subtle);
        background: var(--surface-card);
        box-shadow: 0 24px 48px -24px rgba(0, 0, 0, 0.6);
    }

    .orca-login-card h1 {
        margin: 0 0 0.25rem;
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .orca-login-card .orca-tagline {
        margin: 0 0 1.5rem;
        font-size: 0.9rem;
        color: var(--muted);
    }

    /* —— App shell (Dwaa-like) —— */
    .orca-mobile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-subtle);
        background: rgba(10, 15, 14, 0.92);
        backdrop-filter: blur(12px);
        position: sticky;
        top: 0;
        z-index: 30;
    }

    @media (min-width: 1024px) {
        .orca-mobile-header { display: none; }
    }

    .orca-logo-mark {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        color: inherit;
    }

    .orca-logo-icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.65rem;
        background: linear-gradient(135deg, var(--brand-hover), var(--brand));
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
        color: #042f2e;
        box-shadow: 0 8px 24px -8px var(--brand-glow);
    }

    .orca-logo-text {
        font-weight: 700;
        font-size: 1.05rem;
        letter-spacing: -0.02em;
    }

    .orca-menu-toggle summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.45rem 0.75rem;
        border-radius: 0.75rem;
        border: 1px solid var(--border-subtle);
        font-size: 0.85rem;
        color: #cbd5d1;
        background: transparent;
    }

    .orca-menu-toggle summary::-webkit-details-marker { display: none; }

    .orca-menu-panel {
        position: absolute;
        inset-inline-end: 0;
        top: calc(100% + 0.35rem);
        width: 16rem;
        max-width: min(16rem, 92vw);
        border-radius: 1rem;
        border: 1px solid var(--border-subtle);
        background: var(--surface-card);
        box-shadow: 0 20px 40px -16px rgba(0, 0, 0, 0.55);
        padding: 0.35rem;
        z-index: 50;
    }

    .orca-shell {
        display: flex;
        min-height: 100dvh;
    }

    @media (min-width: 1024px) {
        .orca-shell { min-height: 100vh; }
    }

    .orca-sidebar {
        display: none;
        width: 17rem;
        flex-shrink: 0;
        flex-direction: column;
        padding: 1.75rem 1.1rem;
        border-inline-end: 1px solid var(--border-subtle);
        background: var(--surface-raised);
    }

    @media (min-width: 1024px) {
        .orca-sidebar { display: flex; }
    }

    .orca-sidebar-brand {
        margin-bottom: 1.5rem;
        padding: 0.65rem 0.85rem;
        border-radius: 0.85rem;
        border: 1px solid var(--border-subtle);
        background: linear-gradient(180deg, rgba(20, 184, 166, 0.08), transparent);
    }

    .orca-nav {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        flex: 1;
    }

    .orca-nav button {
        width: 100%;
        text-align: start;
        border: none;
        background: transparent;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.9rem;
        font-weight: 500;
        padding: 0.65rem 0.85rem;
        border-radius: 0.75rem;
        color: #94a3a8;
        transition: background 0.15s, color 0.15s;
    }

    .orca-nav button:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #e2e8f0;
    }

    .orca-nav button.active {
        color: #ccfbf1;
        background: linear-gradient(135deg, var(--brand-muted), transparent);
        box-shadow: inset 0 0 0 1px rgba(13, 148, 136, 0.35);
    }

    .orca-sidebar-logout {
        margin-top: 1rem;
        width: 100%;
        padding: 0.65rem 0.85rem;
        border-radius: 0.75rem;
        border: 1px solid var(--border-subtle);
        background: transparent;
        color: #94a3a8;
        font-family: inherit;
        font-size: 0.9rem;
        cursor: pointer;
        transition: border-color 0.15s, background 0.15s, color 0.15s;
    }

    .orca-sidebar-logout:hover {
        border-color: rgba(13, 148, 136, 0.4);
        background: var(--brand-muted);
        color: #ccfbf1;
    }

    .orca-main {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    .orca-desktop-header {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.75rem;
        border-bottom: 1px solid var(--border-subtle);
        background: linear-gradient(180deg, rgba(20, 28, 26, 0.65) 0%, transparent 100%);
    }

    @media (min-width: 1024px) {
        .orca-desktop-header { display: flex; }
    }

    .orca-desktop-header h2 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 600;
        color: #fff;
    }

    .orca-desktop-header .orca-sub {
        margin: 0.2rem 0 0;
        font-size: 0.8rem;
        color: var(--muted);
    }

    .orca-user-pill {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 1rem;
        border-radius: 1rem;
        border: 1px solid var(--border-subtle);
        background: var(--surface-card);
        font-size: 0.85rem;
    }

    .orca-user-pill span.role { color: var(--brand-hover); font-weight: 600; }

    .orca-mobile-title {
        padding: 1rem 1rem 0.75rem;
        border-bottom: 1px solid var(--border-subtle);
    }

    @media (min-width: 1024px) {
        .orca-mobile-title { display: none; }
    }

    .orca-mobile-title h2 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #fff;
    }

    .orca-content {
        flex: 1;
        padding: 1rem;
    }

    @media (min-width: 1024px) {
        .orca-content { padding: 1.75rem 2rem 2rem; }
    }

    /* —— Components (used by JS templates) —— */
    .orca-hidden { display: none !important; }

    .orca-page.hidden { display: none !important; }

    .orca-card {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid var(--border-subtle);
        background: var(--surface-card);
        padding: 1.25rem 1.35rem;
        margin-bottom: 1rem;
        box-shadow: 0 8px 28px -12px rgba(0, 0, 0, 0.45);
    }

    .orca-card h2 {
        margin: 0 0 1rem;
        font-size: 1rem;
        font-weight: 600;
        color: #f1f5f9;
    }

    .orca-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }

    .orca-stat {
        text-align: center;
        padding: 0.5rem 0;
    }

    .orca-stat .num {
        font-size: 1.65rem;
        font-weight: 700;
        color: #5eead4;
        font-variant-numeric: tabular-nums;
    }

    .orca-stat .lbl {
        font-size: 0.8rem;
        color: var(--muted);
        margin-top: 0.25rem;
    }

    .orca-charts {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }

    .orca-table-wrap { overflow-x: auto; }

    table.orca-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    table.orca-table th,
    table.orca-table td {
        padding: 0.65rem 0.5rem;
        text-align: right;
        border-bottom: 1px solid var(--border-subtle);
    }

    table.orca-table th {
        color: var(--muted);
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    table.orca-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }

    .orca-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.5rem 1rem;
        border-radius: 0.65rem;
        border: none;
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.15s, transform 0.1s;
    }

    .orca-btn:active { transform: scale(0.98); }

    .orca-btn-primary {
        background: linear-gradient(135deg, var(--brand-hover), var(--brand));
        color: #042f2e;
        box-shadow: 0 8px 24px -8px var(--brand-glow);
    }

    .orca-btn-primary:hover { opacity: 0.95; }

    .orca-btn-danger {
        background: var(--danger-muted);
        color: #fda4af;
        border: 1px solid rgba(244, 63, 94, 0.35);
    }

    .orca-btn-danger:hover { background: rgba(244, 63, 94, 0.2); }

    .orca-btn-sm { padding: 0.3rem 0.6rem; font-size: 0.8rem; }

    .orca-flex {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: flex-end;
    }

    .orca-flex > * { flex: 1; min-width: 120px; }

    label.orca-label {
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.8rem;
        color: var(--muted);
    }

    .orca-input,
    .orca-login-card input[type="email"],
    .orca-login-card input[type="password"] {
        width: 100%;
        padding: 0.55rem 0.75rem;
        border-radius: 0.65rem;
        border: 1px solid var(--border-subtle);
        background: rgba(0, 0, 0, 0.25);
        color: var(--text);
        font-family: inherit;
        font-size: 0.9rem;
        margin-bottom: 0.85rem;
    }

    .orca-input:focus,
    .orca-login-card input:focus {
        outline: none;
        border-color: rgba(13, 148, 136, 0.5);
        box-shadow: 0 0 0 3px var(--brand-muted);
    }

    .orca-alert {
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }

    .orca-alert-error {
        border: 1px solid rgba(244, 63, 94, 0.35);
        background: rgba(127, 29, 29, 0.25);
        color: #fecdd3;
    }

    .orca-login-card .orca-btn-primary {
        width: 100%;
        margin-top: 0.25rem;
    }

    .orca-input[type="file"] {
        padding: 0.5rem;
        cursor: pointer;
    }

    .orca-input[type="file"]::file-selector-button {
        margin-inline-end: 0.75rem;
        padding: 0.4rem 0.85rem;
        border-radius: 0.5rem;
        border: 1px solid var(--border-subtle);
        background: var(--brand-muted);
        color: #ccfbf1;
        font-family: inherit;
        font-size: 0.8rem;
        cursor: pointer;
    }

    #import-result {
        background: rgba(0, 0, 0, 0.35);
        border-radius: 0.65rem;
        padding: 1rem;
        font-size: 0.75rem;
        overflow: auto;
        color: #a7f3d0;
        border: 1px solid var(--border-subtle);
    }
</style>
