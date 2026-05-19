# Orca Med — Architecture

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
    Middleware/          # EnsureUserIsAdmin, CheckPharmacyAccess
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
SaleImportService::process()
├── validateExcelSchema()     # أعمدة مطلوبة
├── mapExcelToDatabase()      # ربط أكواد + صيدليات
├── detectDuplicates()        # import_hash داخل الملف + DB
├── bulkInsert()              # chunks
└── generateErrorReport()     # CSV للأخطاء
```

### Duplicate detection

`import_hash = sha256(product_id|pharmacy_id|sold_at|quantity)`

### API

`POST /api/v1/admin/sales/import` — multipart `file` — Admin only

---

## 3. Companies (not manufacturer string)

### Table: `companies`

```
id, name, contact_email, contact_phone, is_active
```

### Relations

- `users.company_id` → Company user يرى بيانات شركته فقط
- `products.company_id` → كل صنف تابع لشركة منتجة

**لا** نستخدم `manufacturer` كنص حر — التحكم عبر `companies`.

---

## User roles

| Role | Write | Read | Pharmacy names |
|------|-------|------|----------------|
| admin | ✅ | ✅ | دائماً حقيقية |
| company | ❌ | ✅ (شركته) | بعد موافقة الأدمن لكل product |

---

## Implementation phases

1. ✅ Foundation: migrations, models, enums, services skeleton
2. Auth + CRUD (provinces → suppliers → pharmacies → products)
3. Sales manual + DashboardService
4. Frontend simple
5. Reports export

---

## Default seed users

| Email | Password | Role |
|-------|----------|------|
| admin@orca-med.test | password | admin |
| company@orca-med.test | password | company |
