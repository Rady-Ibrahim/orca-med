# Orca Med — خطة تنفيذ واجهة Stitch (صفحات Blade مستقلة)

> **الهدف:** استبدال SPA الحالية (`/app` + `orca-app.js` ~560 سطر) بهيكل مثل مشروع **Dwaa**: كل شاشة = Route + Controller + Blade، مع إعادة استخدام **Services/API** الموجودة في الخلفية.
>
> **مرجع التصميم:** لقطات Google Stitch المرفقة (لوحة قيادة، محافظات، موردون، صيدليات، منتجات، مبيعات، تقارير، بحث، مستخدمون، استيراد Excel).
>
> **مرجع التنفيذ:** `E:\projects\Dwaa` — خاصة `routes/web.php`، `layouts/admin.blade.php`، `dashboard/uploads.blade.php`، `DashboardUploadsController`.
>
> **الفلو المعتمد (شركة + رفع + كود تفعيل):** [PRODUCT_FLOW.md](./PRODUCT_FLOW.md) — يُحدَّث قبل Stitch؛ التنفيذ يبدأ من **المرحلة F** ثم الواجهة.

---

## 1. الوضع الحالي vs المطلوب

| الجانب | الحالي | المطلوب (Stitch + Dwaa) |
|--------|--------|-------------------------|
| المسارات | `/` login، `/app` SPA واحدة | `/dashboard`, `/provinces`, `/sales`, … كل صفحة URL مستقل |
| البيانات | `fetch` من `orca-api.js` | Controllers تستدعي **Services** مباشرة (أو Form → نفس Service) |
| الرفع | JS → `POST /api/v1/sales/import` | Form POST → `DashboardImportController@store` → `SaleImportService` (مثل Dwaa) |
| التصميم | `orca-styles` داكن/teal | تطوير `layouts/app.blade.php` ليطابق Stitch (أزرق/أبيض، sidebar، KPI، جداول) |
| الصلاحيات | مخفية بـ JS (`admin-only`) | Middleware Laravel: `auth`, `role:admin`, `role:company`, `role:warehouse` |

**ما هو جاهز في الـ Backend (لا تعيد بناءه — اربطه فقط):**

- Auth API (Sanctum) + أدوار: `admin`, `company`, `warehouse`
- CRUD Services لكل الكيانات
- `SaleImportService` + `ProcessSaleImportJob` + `UploadBatch`
- `DashboardService`, `ReportService`, `CompanyAnalyticsService`
- Rollups + `PharmacyAccessService` (يُستبدل جزئياً بكود التفعيل — انظر PRODUCT_FLOW)
- `config/sale_import.php` (أعمدة قديمة: كود صنف + محافظة من الشيت — **تحتاج تحديث**)
- **غير موجود بعد:** `activation_codes`، `upload_batches.company_id`، `sales.unit_price` / `discount`، مطابقة `product_name` من الشيت

---

## 2. هيكل الملفات المستهدف (بعد التنفيذ)

```
app/Http/Controllers/Web/
  AuthController.php
  DashboardController.php
  ProvinceController.php
  SupplierController.php
  PharmacyController.php
  ProductController.php
  SaleController.php
  ReportController.php
  SearchController.php
  ImportController.php              # رفع Excel (نمط Dwaa)
  UploadBatchController.php
  UserController.php
  CompanyController.php
  WarehouseController.php
  PharmacyAccessRequestController.php
  CompanyAnalyticsController.php    # مستخدم شركة
  SettingsController.php            # لاحقاً

app/Http/Middleware/
  EnsureRole.php                    # admin | company | warehouse

resources/views/
  layouts/
    app.blade.php                   # sidebar + topbar (Stitch)
    guest.blade.php                 # login
  components/
    kpi-card.blade.php
    data-table.blade.php
    status-badge.blade.php
    page-header.blade.php
  dashboard/
    admin.blade.php
    company.blade.php
    warehouse.blade.php
  provinces/
    index.blade.php
    create.blade.php
    edit.blade.php
  suppliers/ ...
  pharmacies/ ...
  products/ ...
  sales/
    index.blade.php
    create.blade.php
  reports/
    index.blade.php                 # Stitch: التقارير الإدارية
  search/
    index.blade.php                 # Stitch: بحث متقدم
  imports/
    index.blade.php                 # رفع + سجل دفعات (Dwaa uploads)
    show.blade.php                  # تفاصيل دفعة + تحميل أخطاء
  users/ ...
  companies/ ...
  warehouses/ ...
  access-requests/
    index.blade.php
  company-analytics/
    products.blade.php
    provinces.blade.php
    pharmacies.blade.php

public/js/
  app.js                            # صغير: sidebar toggle، confirm delete فقط
  charts.js                         # Chart.js لصفحات فيها رسوم فقط

routes/web.php                      # كل المسارات هنا
```

**ملاحظة:** يمكن الإبقاء على API `/api/v1/*` للموبايل/تكامل لاحقاً؛ الويب يستخدم Session أو Sanctum cookie حسب اختياركم في المهمة 0.2.

---

## 3. خريطة شاشات Stitch → Routes

| # | شاشة Stitch | Route مقترح | أدوار | Controller |
|---|-------------|-------------|-------|------------|
| S1 | لوحة قيادة (Admin) | `GET /dashboard` | admin | `DashboardController@admin` |
| S2 | لوحة قيادة (Company) | `GET /dashboard` | company | `DashboardController@company` |
| S3 | لوحة مخزن | `GET /dashboard` | warehouse | `DashboardController@warehouse` |
| S4 | إدارة المحافظات | `GET /provinces` | admin | `ProvinceController@index` |
| S5 | إدارة الموردين | `GET /suppliers` | admin | `SupplierController@index` |
| S6 | إدارة الصيدليات | `GET /pharmacies` | admin | `PharmacyController@index` |
| S7 | إدارة المنتجات | `GET /products` | admin, company (قراءة) | `ProductController@index` |
| S8 | تتبع المبيعات | `GET /sales` | admin, company (قراءة) | `SaleController@index` |
| S9 | التقارير الإدارية | `GET /reports` | admin, company | `ReportController@index` |
| S10 | بحث متقدم | `GET /search` | admin, company | `SearchController@index` |
| S11 | استيراد Excel | `GET /imports` | **admin** (أساسي) | `ImportController@index` — شركة + مورد + محافظة + ملف |
| S11b | تفعيل كود (شركة) | `GET /activate` | company | `ActivationController@index` |
| S11c | أكواد التفعيل | `GET /activation-codes` | admin | `ActivationCodeController@index` (مثل Dwaa) |
| S12 | إدارة المستخدمين | `GET /users` | admin | `UserController@index` |
| S13 | تحليلات الشركة (تفصيلي) | `GET /analytics/products` | company | `CompanyAnalyticsController@products` |
| S14 | طلبات وصول (قديم — اختياري) | `GET /access-requests` | admin | يُؤجَّل إن استُبدل بالكود |
| S15 | الشركات + مستخدمون | `GET /companies` | admin | إنشاء حسابات دخول الشركات |
| S16 | المخازن (اختياري) | `GET /warehouses` | admin | ليس في مسار الرفع الرئيسي |

---

## 4. الفلو الكامل

**التفاصيل الكاملة (شيتك، كود التفعيل، توحيد مخزن/صيدلية):** [PRODUCT_FLOW.md](./PRODUCT_FLOW.md)

### 4.1 ملخص الرفع (الأولوية)

1. الأدمن يفتح **رفع Excel** ويختار: **الشركة** + **المورد** + **المحافظة** + الملف.
2. القراءة **صفاً صفاً** (مثل Dwaa) من أعمدة: **اسم النقطة** (مخزن/صيدلية = نفس الشيء)، كمية، **اسم الصنف**، تاريخ، سعر، خصم.
3. كل صف → `pharmacies.name` من العمود الأول + `sales` مربوطة بمنتجات **نفس الشركة** فقط.
4. بعد النجاح → تحديث rollups → الشركة ترى إحصائيات أصنافها.

### 4.2 ملخص دخول الشركة

1. دخول بالإيميل الذي أنشأه الأدمن.
2. **دائماً:** إحصائيات عامة لكل صنف + **فلتر تقويم (من–إلى)**.
3. **بعد كود التفعيل:** تفاصيل كاملة (أسماء نقاط البيع، أسعار، خصم، تعمق محافظة/صيدلية) لمدة N يوماً.

### 4.3 الأدمن

- CRUD + إنشاء مستخدمي شركات + **إدارة أكواد التفعيل** (عدد الأيام، حد الاستخدام).
- رفع Excel (ليس اعتماداً على `warehouse_id` في المسار الرئيسي).

---

## 5. قائمة المهام — للتنفيذ تدريجياً

**حالة:** `⬜` لم يبدأ · `🔄` جاري · `✅` منتهي

### المرحلة F — الفلو المعتمد (قبل Stitch — أولوية)

| ID | المهمة | التفاصيل | معيار القبول |
|----|--------|----------|--------------|
| **T-F.1** | Migration دفعة ومبيعات | `upload_batches`: `company_id`, `supplier_id`, `province_id`؛ `sales`: `unit_price`, `discount` | migrate OK |
| **T-F.2** | `config/sale_import.php` | `outlet_name`, `product_name`, `quantity`, `sold_at`, `unit_price`, `discount` + aliases عربية | يقرأ شيتك الفعلي |
| **T-F.3** | `SaleImportService` | سياق الدفعة (شركة/مورد/محافظة)؛ مطابقة صنف بالاسم؛ outlet → `pharmacies` فقط؛ تخطي «رصيد اول المده» | استيراد تجريبي ناجح |
| **T-F.4** | API رفع | `POST /sales/import` يطلب `company_id`, `supplier_id`, `province_id` + file | 202 + batch |
| **T-F.5** | `activation_codes` + Service | مثل Dwaa؛ Admin CRUD | كود يمدد الصلاحية |
| **T-F.6** | تفعيل شركة | `POST /activation` + `users.analytics_unlock_expires_at` | بعد الكود تظهر التفاصيل |
| **T-F.7** | Analytics + تاريخ | `CompanyAnalyticsService` يقبل `from`/`to`؛ masking = كود فعّال | فلتر تقويم يعمل |
| **T-F.8** | إهمال مسار warehouse في الرفع | الرفع الأساسي بدون `warehouse_id`؛ توثيق في ARCHITECTURE | PRODUCT_FLOW متطابق مع الكود |

### المرحلة 0 — الأساس (يُنفَّذ أولاً)

| ID | المهمة | التفاصيل | معيار القبول |
|----|--------|----------|--------------|
| **T-0.1** | Layout Stitch | إنشاء `layouts/app.blade.php`: sidebar (قائمة PRD)، topbar (بحث سريع placeholder)، منطقة محتوى، مكونات `page-header`, `kpi-card` | أي صفحة تجريبية تظهر بنفس هيكل Stitch |
| **T-0.2** | مصادقة ويب | `Web\AuthController`: login form POST، session أو Sanctum SPA token في cookie؛ logout؛ middleware `auth` | بعد login يُوجَّه حسب الدور؛ `/app` القديمة redirect للـ dashboard |
| **T-0.3** | Middleware أدوار | `EnsureRole:admin,company,warehouse` على مجموعات routes | company لا يصل `/users`؛ warehouse لا يصل CRUD إدارة |
| **T-0.4** | Navigation حسب الدور | partial `partials/sidebar-nav.blade.php` يعرض عناصر مختلفة لكل role (جدول S1–S16) | القائمة تطابق الصلاحيات في PRD |
| **T-0.5** | إهلاك SPA | الإبقاء على `orca-app.js` مؤقتاً أو حذفه بعد T-1.1؛ تحديث `docs/API.md` بمسارات الويب | لا اعتماد على `showPage()` للتنقل الرئيسي |

**مرجع Dwaa:** `layouts/admin.blade.php`, `WebAuthController`, `$nav` array.

---

### المرحلة 1 — لوحات القيادة

| ID | المهمة | Service / بيانات | Stitch |
|----|--------|------------------|--------|
| **T-1.1** | `DashboardController@admin` | `DashboardService::getStats()` + charts | S1 — KPIs + 3 charts |
| **T-1.2** | `DashboardController@company` | نفس Service مع scope شركة؛ إخفاء أزرار الكتابة | S5 company variant |
| **T-1.3** | `DashboardController@warehouse` | إحصائيات مخزن (`warehouse_id`) | — |
| **T-1.4** | `charts.js` | تهيئة Chart.js من بيانات server-side `@json($charts)` | أشرطة المبيعات / المحافظات |

---

### المرحلة 2 — CRUD إداري (صفحة قائمة + create/edit)

لكل وحدة: **index** (جدول + فلاتر + pagination) + **create/store** + **edit/update** + **destroy** (مع `@csrf` + confirm).

| ID | الوحدة | Web Controller | Service موجود | Stitch | فلاتر PRD |
|----|--------|----------------|---------------|--------|-----------|
| **T-2.1** | محافظات | `Web\ProvinceController` | `ProvinceService` | S4 | بحث بالاسم |
| **T-2.2** | موردون | `Web\SupplierController` | `SupplierService` | S5 | محافظة، حالة، بحث |
| **T-2.3** | صيدليات | `Web\PharmacyController` | `PharmacyService` | S6 | محافظة، مورد، بحث اسم/هاتف |
| **T-2.4** | منتجات | `Web\ProductController` | `ProductService` | S7 | **بحث اسم/كود**، شركة (admin) |
| **T-2.5** | مبيعات | `Web\SaleController` | `SaleService` | S8 | تاريخ، محافظة، مورد، صنف |
| **T-2.6** | مخازن | `Web\WarehouseController` | `WarehouseService` | — | + `ensureShadowSupplier` عند الإنشاء |
| **T-2.7** | شركات | `Web\CompanyController` | `CompanyService` | — | + form كلمة مرور حساسة |
| **T-2.8** | مستخدمون | `Web\UserController` | `UserService` | S12 | دور، شركة، مخزن |

**مكونات UI مشتركة (تُنشأ مرة في T-2.0):**

| ID | المهمة |
|----|--------|
| **T-2.0** | `components/data-table`, `status-badge`, flash messages, pagination Laravel |

---

### المرحلة 3 — رفع البيانات (ويب — بعد T-F.*)

| ID | المهمة | التفاصيل |
|----|--------|----------|
| **T-3.1** | صفحة `imports/index` | Form: **`company_id`, `supplier_id`, `province_id`**, `file`؛ جدول batches |
| **T-3.2** | `ImportController@store` | يستدعي `createQueuedBatch` بالسياق الجديد + redirect |
| **T-3.3** | `imports/{batch}` | تفاصيل + CSV أخطاء |
| **T-3.4** | قالب Excel | أعمدة شيتك (outlet, product name, qty, date, price, discount) |
| **T-3.5** | صلاحيات | **admin** للرفع الرئيسي |
| **T-IMP-2** | (لاحق) معالج 3 خطوات Stitch | اختياري |

**مرجع Dwaa:** `dashboard/uploads.blade.php` + `supplier_id` → عندنا **`company_id` + `supplier_id` + `province_id`**.

---

### المرحلة 4 — تقارير وبحث

| ID | المهمة | Service | Stitch |
|----|--------|---------|--------|
| **T-4.1** | `reports/index` | `ReportService` — top/bottom products، by province | S9 |
| **T-4.2** | تصدير مبيعات | `GET /reports/sales/export` — download CSV | أزرار Export في Stitch |
| **T-4.3** | `search/index` | `ReportService::search` أو dedicated | S10 — فلاتر: صنف، محافظة، مورد، صيدلية، شركة، تاريخ |
| **T-4.4** | masking في البحث | تمرير `product_id` عند الحاجة؛ Blade يعرض 🔒 | PRD حساسية |

---

### المرحلة 5 — تجربة مستخدم الشركة

| ID | المهمة | ملاحظات |
|----|--------|---------|
| **T-5.0** | فلتر تقويم | `from` / `to` في layout تحليلات الشركة — يمر لكل API/Service |
| **T-5.1** | `products/index` read-only | بحث بالاسم |
| **T-5.2** | `analytics/products` | KPIs عامة دائماً (كمية، محافظات مجمّعة) |
| **T-5.3** | `analytics/.../provinces` | نفس الفلتر الزمني |
| **T-5.4** | `analytics/.../pharmacies` | masked بدون كود؛ يظهر الاسم + سعر/خصم مع كود فعّال |
| **T-5.5** | `activate` | صفحة إدخال كود (بديل sensitive-unlock) |
| **T-5.6** | (اختياري) طلب وصول قديم | يُؤجَّل إن لم يُطلب |

---

### المرحلة 6 — إدارة النظام

| ID | المهمة |
|----|--------|
| **T-6.1** | `access-requests/index` — قائمة pending + approve/reject (POST) |
| **T-6.2** | `settings/index` — placeholder (لغة، TTL حساسية من `config/orca.php`) |
| **T-6.3** | مقارنة فترات (اختياري) | `CompanyAnalyticsService::comparePeriods` — form تاريخين |

---

### المرحلة 7 — جودة وتسليم

| ID | المهمة |
|----|--------|
| **T-7.1** | تحديث `docs/API.md` + `ARCHITECTURE.md` بمسارات الويب |
| **T-7.2** | Feature tests لـ Web auth + imports + company read-only |
| **T-7.3** | Seed بيانات كافية لعرض Stitch (محافظات، مبيعات، rollup) |
| **T-7.4** | إزالة `/app` SPA أو تحويلها لـ redirect فقط |

---

## 6. ترتيب التنفيذ المقترح (سبرنتات)

```
Sprint 0 (فلو):      T-F.1 → T-F.8   ← قبل أي Stitch
Sprint A (ويب أساس): T-0.1 → T-0.5 → T-3.1 → T-3.3 → T-5.0 → T-5.5
Sprint B:            T-1.x → T-2.x (CRUD)
Sprint C:            T-5.2 → T-5.4 → T-4.x
Sprint D:            T-F.5 admin activation codes UI → T-2.7 users/companies
```

**ابدأ التنفيذ مع المستخدم على:** `T-F.1` (migrations) ثم `T-F.3` (استيراد الشيت).

---

## 7. قرارات تقنية (يُفضَّل تثبيتها قبل T-0.2)

| السؤال | خيار A (موصى به مثل Dwaa) | خيار B |
|--------|---------------------------|--------|
| جلسة الويب | **Laravel Session** + `Auth::login()` بعد التحقق من API credentials | Sanctum token في `localStorage` + استدعاء API من Blade (نفس الوضع الحالي) |
| CSRF | Forms تقليدية `@csrf` | — |
| حذف | `DELETE` form مع `@method('DELETE')` | AJAX |
| Charts | بيانات من Controller `@json` | API fetch |

**التوصية:** **خيار A** — Controllers تستدعي Services مباشرة؛ API يبقى للتكامل الخارجي. هذا يقلل JS ويطابق Dwaa.

---

## 8. ربط أعمدة Excel (معتمد — انظر PRODUCT_FLOW)

| من الشيت | مطلوب | ملاحظة |
|----------|--------|--------|
| اسم المخزن/الصيدلية (`outlet_name`) | نعم | → `pharmacies.name` فقط |
| الكمية | نعم | |
| اسم الصنف (`product_name`) | نعم | ضمن `products` لنفس `company_id` للدفعة |
| التاريخ | نعم | `DD/MM/YYYY` |
| السعر | نعم | `sales.unit_price` |
| الخصم | نعم | `sales.discount` |
| شركة / مورد / محافظة | — | **من نموذج الرفع** وليس من الشيت |

---

## 9. سجل التقدم

| ID | الحالة | تاريخ | ملاحظات |
|----|--------|-------|---------|
| T-0.1 | ✅ | 2026-05-23 | `layouts/app.blade.php` + `components/kpi-card`, `page-header`, `status-badge` + `partials/sidebar-nav` |
| T-0.2 | ✅ | 2026-05-23 | `Web\AuthController` (Session Auth) + `login.blade.php` → form POST + `DashboardController` + dashboard views (admin/company/warehouse) |
| T-0.3 | ✅ | 2026-05-23 | `EnsureRole` middleware (alias `role`) — registered in `bootstrap/app.php`; all routes grouped by role in `routes/web.php` |
| T-0.4 | ✅ | 2026-05-23 | `partials/sidebar-nav.blade.php` — nav items per role (admin/company/warehouse) |
| T-0.5 | 🔄 | | `/app` redirects to `/dashboard`; SPA still intact — remove after T-1.1+ |
| T-2.0 | ✅ | 2026-05-23 | `components/data-table.blade.php` — shared wrapper (filters slot + pagination) |
| T-2.1 | ✅ | 2026-05-23 | `ProvinceController` real + `provinces/index`, `create`, `edit` |
| T-2.2 | ✅ | 2026-05-23 | `SupplierController` real + `suppliers/index`, `create`, `edit`, `_form` |
| T-2.3 | ✅ | 2026-05-23 | `PharmacyController` real + `pharmacies/index`, `create`, `edit`, `_form` |
| T-2.6 | ✅ | 2026-05-23 | `WarehouseController` real + views + `ensureShadowSupplier` on store |
| T-2.7 | ✅ | 2026-05-23 | `CompanyController` real + views + sensitive password field |
| T-2.8 | ✅ | 2026-05-23 | `UserController` real + views + dynamic role fields (JS) |
| … | | | |

> عند إنهاء أي مهمة: غيّر `⬜` إلى `✅` وأضف تاريخاً وPR/commit إن وُجد.

---

## 10. ما لا يُبنى في هذه الخطة (خارج النطاق)

- تطبيق موبايل منفصل
- ربط ERP/API مخازن (مذكور في PRD كمستقبلي)
- مستوى جغرافي أدق من المحافظة (مركز/حي)
- معالج استيراد 3 خطوات كامل (إلا في T-IMP-2)
- Modular Monolith منفصل — نبقى على Service layer الحالي

---

*آخر تحديث: 2026-05-23 — يعكس كود المشروع بعد إضافة warehouses، rollups، company analytics API، و SPA مؤقتة في `/app`.*
