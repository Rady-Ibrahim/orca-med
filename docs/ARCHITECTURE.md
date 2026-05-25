# Orca Med — Architecture

> **فلو المنتج المعتمد (رفع الشيت، شركة، كود تفعيل):** [PRODUCT_FLOW.md](./PRODUCT_FLOW.md)  
> **خطة واجهة Stitch:** [STITCH_UI_TASKS.md](./STITCH_UI_TASKS.md)

## Stack

- Laravel 12 + Sanctum (API auth)
- Service Pattern (not modular)
- Maatwebsite Excel (sales import)
- Simple frontend later (consumes API only)

## Folder structure

```
app/
  DTOs/
  Enums/
  Http/
    Controllers/Api/
    Middleware/          # EnsureUserIsAdmin, EnsureAdminOrWarehouseUpload, CheckPharmacyAccess
    Resources/           # Mask pharmacy names when needed
  Models/
  Services/              # Business logic
config/
  sale_import.php        # Excel schema
routes/
  api.php
```

---

## 1. Sensitive data protection (Two-step access)

### Table: `pharmacy_access_requests`

| Column | Description |
|--------|-------------|
| company_id | الشركة الطالبة |
| product_id | الصنف المطلوب رؤية صيدلياته |
| requested_by | مستخدم الشركة |
| status | pending / approved / rejected |
| approved_by, approved_at, rejected_at | بيانات قرار الأدمن |

### Flow

1. **Company user** يطلب وصول: `POST /api/v1/pharmacy-access-requests` + `product_id`
2. **Admin** يوافق أو يرفض:  
   - `POST /api/v1/admin/pharmacy-access-requests/{id}/approve`  
   - `POST /api/v1/admin/pharmacy-access-requests/{id}/reject`
3. عند عرض صيدليات/مبيعات مرتبطة بصنف: Middleware `CheckPharmacyAccess` يضبط `mask_pharmacies` على الـ Request
4. `PharmacyResource` / `SupplierResource` يعرضان:
   - بدون موافقة: `Pharmacy #1`, `Pharmacy #2` — بدون هاتف/عنوان
   - بعد الموافقة: الأسماء الحقيقية

5. **Company user** (اختياري): `POST /api/v1/sensitive-unlock` + `password` — يطابق `companies.sensitive_view_password` (يضبطها الأدمن). عند النجاح يُحدَّث `users.sensitive_unlock_expires_at` (مدة من `config/orca.php`).
6. `PharmacyAccessService` يدمج: موافقة الطلب **أو** جلسة الفتح المؤقتة — مستخدمو المخازن يرون الأسماء دون قناع.

**ملاحظة:** مرّر `product_id` في query أو route على endpoints التي تعرض بيانات حساسة.

---

## 2. Excel import — `SaleImportService`

منفصل عن `SaleService` (تسجيل يدوي فردي).

### Table: `upload_batches`

تتبع كل عملية رفع: ملف، حالة، عدد نجاح/أخطاء/تكرار، مسار تقرير الأخطاء.

### Table: `upload_batch_errors`

صف لكل خطأ: رقم الصف، العمود، النوع، الرسالة، `row_data` JSON.

### Expected Excel columns

| Canonical | Arabic aliases (examples) |
|-----------|---------------------------|
| product_code | كود الصنف |
| quantity | الكمية |
| pharmacy_name | الصيدلية |
| province_name | المحافظة |
| supplier_name | المورد (اختياري إن وُجد مورد واحد) |
| sold_at | تاريخ البيع |

Config: `config/sale_import.php`

### Pipeline

```
SaleImportService::createQueuedBatch()   # صف في upload_batches (Queued)
ProcessSaleImportJob (afterResponse)      # بدون timeout على الطلب HTTP
├── processBatch()
└── AnalyticsRollupService::rebuildForProductIds()
```

(التفاصيل السابقة لـ `validateExcelSchema` وغيرها ما زالت داخل `processBatch` / الخدمة.)

### Duplicate detection

`import_hash = sha256(product_id|pharmacy_id|sold_at|quantity)`

### API

`POST /api/v1/sales/import` — multipart `file` — **Admin** (مع `warehouse_id`) أو **مستخدم مخزن** (يُستنتج المخزن من الحساب).  
`GET /api/v1/upload-batches` — قائمة الدفعات للمدير أو لنفس المخزن.

---

## 3. Warehouses & data flow

- جدول `warehouses` (نوع: جملة / قطاعي)، و`users.warehouse_id` لمستخدمي المخزن.
- `sales.warehouse_id` و`upload_batches.warehouse_id` لربط الرفع والمبيعات بالمخزن.
- `suppliers.warehouse_id` + **shadow supplier** لكل مخزن (لتوافق `supplier_id` على الصيدليات القديمة).

### Admin API

`apiResource` — `GET|POST|... /api/v1/admin/warehouses`

---

## 4. Company analytics (قراءة مجمّعة)

جداول تجميع (تحديث بعد الاستيراد الناجح):

- `analytics_product_rollups`
- `analytics_product_province_rollups`
- `analytics_product_pharmacy_rollups`

**Endpoints** (مستخدم شركة فقط في المنطق):

- `GET /api/v1/company/analytics/products?search=`
- `GET /api/v1/company/analytics/products/{product}/provinces`
- `GET /api/v1/company/analytics/products/{product}/provinces/{province}/pharmacies`
- `GET /api/v1/company/analytics/compare` — مقارنة فترتين (من `sales` حسب `company_id`)

---

## 5. Companies (not manufacturer string)

### Table: `companies`

```
id, name, contact_email, contact_phone, is_active, sensitive_view_password (hashed, nullable)
```

### Relations

- `users.company_id` → Company user يرى بيانات شركته فقط
- `products.company_id` → كل صنف تابع لشركة منتجة

**لا** نستخدم `manufacturer` كنص حر — التحكم عبر `companies`.

---

### Admin: ضبط كلمة المرور الإضافية

`PATCH /api/v1/admin/companies/{company}/sensitive-view-password`  
Body JSON: `{ "sensitive_view_password": "..." }` — نص فارغ أو `null` لإلغاء الضبط (مع قاعدة التحقق في الـ Controller).

---

## User roles

| Role | Write | Read | Pharmacy names |
|------|-------|------|----------------|
| admin | ✅ | ✅ | دائماً حقيقية |
| company | ❌ | ✅ (شركته) | بعد موافقة الأدمن لكل product **أو** جلسة `sensitive-unlock` |
| warehouse | رفع Excel / دفعات | حسب النطاق | غالباً كامل للصيدليات المرتبطة بالمخزن |

---

## Implementation phases

1. ✅ Foundation: migrations, models, enums, services skeleton
2. ✅ Auth + CRUD (provinces → suppliers → pharmacies → products)
3. ✅ Sales manual + DashboardService + ReportService
4. ✅ Frontend simple (`/`, `/app`)
5. ✅ Reports export (`GET /api/v1/reports/sales/export`)

---

## Default seed users

| Email | Password | Role |
|-------|----------|------|
| admin@orca-med.test | password | admin |
| company@orca-med.test | password | company |
| warehouse@orca-med.test | password | warehouse |

**تجربة القفل:** للشركة التجريبية يُضبط `sensitive_view_password` في الـ seeder على `secret123` (يمكن تغييره من الـ API أعلاه).
