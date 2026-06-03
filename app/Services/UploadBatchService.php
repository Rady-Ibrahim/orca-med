<?php

namespace App\Services;

use App\Models\UploadBatch;
use App\Models\User;
use App\Models\UploadBatchError;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\Sale;
use App\Models\Pharmacy;
use App\Services\QuantitySummaryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UploadBatchService
{
    public const ERROR_AMBIGUOUS_PRODUCT = 'ambiguous_product';

    public function __construct(
        private readonly QuantitySummaryService $quantitySummaryService,
    ) {}

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = UploadBatch::query()
            ->with(['uploader', 'warehouse'])
            ->withCount('errors')
            ->when($user->isWarehouseUser(), fn ($q) => $q->where('warehouse_id', $user->warehouse_id))
            ->orderByDesc('created_at');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findForUser(int $id, User $user): UploadBatch
    {
        $batch = UploadBatch::with(['uploader', 'warehouse', 'errors'])->findOrFail($id);

        if ($user->isWarehouseUser() && $batch->warehouse_id !== $user->warehouse_id) {
            abort(403, 'غير مصرح.');
        }

        return $batch;
    }

    public function ensureAdminForBatch(UploadBatch $batch, User $user): void
    {
        if (! $user->isAdmin()) {
            abort(403, 'فقط الأدمن يمكنه تنفيذ هذه العملية.');
        }

        $this->findForUser($batch->id, $user);
    }

    public function resolveAmbiguousProduct(UploadBatch $batch, array $data, User $user): array
    {
        $error = $batch->errors()
            ->where('error_type', self::ERROR_AMBIGUOUS_PRODUCT)
            ->where('row_number', $data['row_number'])
            ->first();

        if (! $error) {
            throw ValidationException::withMessages([
                'row_number' => ['لم يتم العثور على الصف المطلوب أو تم معالجته بالفعل.'],
            ]);
        }

        $rowData = $error->row_data ?? [];
        $raw = $rowData['raw'] ?? [];
        $mapped = $rowData['mapped'] ?? [];

        // Find or create pharmacy from raw data
        $outletName = trim((string) ($raw['outlet_name'] ?? ''));
        $pharmacyId = null;

        if (! empty($outletName)) {
            $pharmacy = Pharmacy::where('name', $outletName)
                ->where('supplier_id', $batch->supplier_id)
                ->where('province_id', $batch->province_id)
                ->first();

            if (! $pharmacy) {
                $pharmacy = Pharmacy::create([
                    'supplier_id' => $batch->supplier_id,
                    'province_id' => $batch->province_id,
                    'upload_batch_id' => $batch->id,
                    'name' => $outletName,
                    'code' => strtoupper(substr(md5($batch->supplier_id . $outletName), 0, 8)),
                ]);
            }

            $pharmacyId = $pharmacy->id;
        }

        // Merge pharmacy_id into mapped data
        $mapped['pharmacy_id'] = $pharmacyId;
        $mapped['supplier_id'] = $mapped['supplier_id'] ?? $batch->supplier_id;
        $mapped['province_id'] = $mapped['province_id'] ?? $batch->province_id;

        if ($data['action'] === 'map_to_existing') {
            if (empty($data['product_id'])) {
                throw ValidationException::withMessages([
                    'product_id' => ['product_id مطلوب عند اختيار map_to_existing.'],
                ]);
            }

            $product = Product::findOrFail($data['product_id']);

            if ($product->company_id !== $batch->company_id) {
                throw ValidationException::withMessages([
                    'product_id' => ['المنتج المختار لا ينتمي إلى نفس الشركة الخاصة بالرفع.'],
                ]);
            }

            if (! empty($raw['product_name'])) {
                ProductAlias::updateOrCreate(
                    ['alias_name' => $raw['product_name']],
                    ['product_id' => $product->id]
                );
            }

            $sale = $this->createSaleFromRow($batch, $mapped, $product->id);
        } else {
            // Generate unique code
            $code = strtoupper(substr(md5($batch->company_id . ($raw['product_name'] ?? uniqid('', true))), 0, 8));

            // Ensure code is unique
            while (Product::where('code', $code)->exists()) {
                $code = strtoupper(substr(md5(uniqid('', true)), 0, 8));
            }

            $product = Product::create([
                'company_id' => $batch->company_id,
                'upload_batch_id' => $batch->id,
                'name' => $raw['product_name'] ?? ('منتج جديد '.$batch->id.'-'.$error->row_number),
                'code' => $code,
                'description' => null,
            ]);

            $sale = $this->createSaleFromRow($batch, $mapped, $product->id);
        }

        $error->delete();

        // Update batch statistics
        $batch->refresh();
        $batch->success_count = Sale::where('upload_batch_id', $batch->id)->count();
        $batch->error_count = $batch->errors()->count();
        $batch->duplicate_count = 0; // Can be calculated if needed

        if ($batch->error_count === 0) {
            $batch->status = \App\Enums\UploadBatchStatus::COMPLETED;
        }

        $batch->save();

        return [
            'message' => 'تمت معالجة الصف بنجاح.',
            'sale_id' => $sale->id,
        ];
    }

    public function deleteBatch(UploadBatch $batch, User $user): void
    {
        $this->ensureAdminForBatch($batch, $user);

        $companyId = $batch->company_id;
        $storedPath = $batch->stored_path;
        $errorReportPath = $batch->error_report_path;

        DB::transaction(function () use ($batch) {
            // Remove batch errors
            $batch->errors()->delete();

            // Delete product aliases for products created by this batch
            $productIds = Product::where('upload_batch_id', $batch->id)->pluck('id');
            if ($productIds->isNotEmpty()) {
                ProductAlias::whereIn('product_id', $productIds)->delete();
            }

            // Delete all products created by this batch
            Product::where('upload_batch_id', $batch->id)->delete();

            // Delete all pharmacies created by this batch
            Pharmacy::where('upload_batch_id', $batch->id)->delete();

            // Remove sales (FK cascade will handle this, but explicit for safety)
            Sale::where('upload_batch_id', $batch->id)->delete();

            $batch->delete();
        });

        if ($storedPath) {
            Storage::disk('local')->delete($storedPath);
        }

        if ($errorReportPath) {
            Storage::disk('local')->delete($errorReportPath);
        }

        $this->quantitySummaryService->rebuildForCompany($companyId);
    }

    public function downloadErrorReport(UploadBatch $batch): ?string
    {
        if (! $batch->error_report_path || ! Storage::disk('local')->exists($batch->error_report_path)) {
            return null;
        }

        return Storage::disk('local')->path($batch->error_report_path);
    }

    private function createSaleFromRow(UploadBatch $batch, array $row, int $productId): Sale
    {
        return Sale::create([
            'product_id' => $productId,
            'pharmacy_id' => $row['pharmacy_id'] ?? null,
            'supplier_id' => $row['supplier_id'] ?? $batch->supplier_id,
            'province_id' => $row['province_id'] ?? $batch->province_id,
            'warehouse_id' => $row['warehouse_id'] ?? $batch->warehouse_id,
            'upload_batch_id' => $batch->id,
            'quantity' => $row['quantity'] ?? 0,
            'sold_at' => $row['sold_at'] ?? now()->format('Y-m-d'),
            'import_hash' => $row['import_hash'] ?? null,
            'unit_price' => $row['unit_price'] ?? null,
            'discount' => $row['discount'] ?? null,
        ]);
    }
}
