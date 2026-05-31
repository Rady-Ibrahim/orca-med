<?php

namespace App\Services;

use App\DTOs\SaleImportResult;
use App\Enums\UploadBatchStatus;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Province;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\UploadBatch;
use App\Models\UploadBatchError;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SaleImportService
{
    private const ERROR_VALIDATION = 'validation';

    private const ERROR_NOT_FOUND = 'not_found';

    private const ERROR_DUPLICATE = 'duplicate';

    public function __construct() {}

    public function createQueuedBatch(
        UploadedFile $file,
        User $user,
        int $companyId,
        int $supplierId,
        int $provinceId
    ): UploadBatch {
        $storedPath = $file->store('imports/sales', 'local');

        return UploadBatch::create([
            'uploaded_by' => $user->id,
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'province_id' => $provinceId,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => UploadBatchStatus::Queued,
        ]);
    }

    public function processBatch(UploadBatch $batch): SaleImportResult
    {
        $batch->update([
            'status' => UploadBatchStatus::Processing,
            'started_at' => $batch->started_at ?? now(),
        ]);

        try {
            $fullPath = Storage::disk('local')->path($batch->stored_path);
            if (! is_readable($fullPath)) {
                return $this->failBatch($batch, 'ملف الاستيراد غير موجود أو غير قابل للقراءة.');
            }

            $rows = Excel::toArray([], $fullPath)[0] ?? [];

            if (empty($rows)) {
                return $this->failBatch($batch, 'الملف فارغ أو لا يحتوي على بيانات.');
            }

            $headerRow = array_shift($rows);
            \Log::info('Import header row', ['batch_id' => $batch->id, 'header' => $headerRow]);
            $mapping = $this->validateExcelSchema($headerRow);

            if ($mapping === null) {
                return $this->failBatch($batch, 'تنسيق الأعمدة غير صحيح. راجع ملف القالب المتوقع.');
            }

            $batch->update(['total_rows' => count($rows)]);

            $mappedRows = $this->mapExcelToDatabase($rows, $mapping, $batch);
            $validRows = $mappedRows->filter(fn (array $row) => empty($row['errors']));
            $invalidRows = $mappedRows->filter(fn (array $row) => ! empty($row['errors']));

            foreach ($invalidRows as $invalid) {
                foreach ($invalid['errors'] as $error) {
                    UploadBatchError::create([
                        'upload_batch_id' => $batch->id,
                        'row_number' => $invalid['row_number'],
                        'column' => $error['column'] ?? null,
                        'error_type' => $error['type'],
                        'message' => $error['message'],
                        'row_data' => $invalid['raw'],
                    ]);
                }
            }

            $duplicateHashes = $this->detectDuplicates($validRows);
            $toInsert = $validRows->reject(fn (array $row) => in_array($row['import_hash'], $duplicateHashes, true));

            $duplicateCount = $validRows->count() - $toInsert->count();

            foreach ($validRows->filter(fn (array $row) => in_array($row['import_hash'], $duplicateHashes, true)) as $dup) {
                UploadBatchError::create([
                    'upload_batch_id' => $batch->id,
                    'row_number' => $dup['row_number'],
                    'error_type' => self::ERROR_DUPLICATE,
                    'message' => 'سجل مكرر داخل الملف أو موجود مسبقاً في النظام.',
                    'row_data' => $dup['raw'],
                ]);
            }

            $inserted = $this->bulkInsert($toInsert, $batch);

            $errorCount = $invalidRows->count() + $duplicateCount;
            $status = match (true) {
                $inserted > 0 && $errorCount === 0 => UploadBatchStatus::Completed,
                $inserted > 0 && $errorCount > 0 => UploadBatchStatus::Partial,
                default => UploadBatchStatus::Failed,
            };

            $errorReportPath = null;
            if ($errorCount > 0) {
                $errorReportPath = $this->generateErrorReport($batch);
            }

            $batch->update([
                'status' => $status,
                'success_count' => $inserted,
                'error_count' => $errorCount,
                'duplicate_count' => $duplicateCount,
                'error_report_path' => $errorReportPath,
                'completed_at' => now(),
            ]);

            return new SaleImportResult(
                batch: $batch->fresh(),
                success: $inserted > 0,
                message: "تم استيراد {$inserted} سجل. أخطاء: {$errorCount}.",
            );
        } catch (\Throwable $e) {
            return $this->failBatch($batch, $e->getMessage());
        }
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>|null column key => index
     */
    public function validateExcelSchema(array $headerRow): ?array
    {
        $normalized = collect($headerRow)->map(fn ($h) => $this->normalizeHeader((string) $h));
        $config = config('sale_import.columns');
        $mapping = [];

        foreach ($config as $canonical => $aliases) {
            $index = $normalized->search(fn (string $h) => in_array($h, array_map([$this, 'normalizeHeader'], $aliases), true));

            if ($index === false) {
                if (in_array($canonical, config('sale_import.required_fields'), true)) {
                    return null;
                }

                continue;
            }

            $mapping[$canonical] = $index;
        }

        foreach (config('sale_import.required_fields') as $required) {
            if (! isset($mapping[$required])) {
                return null;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int>  $mapping
     * @return Collection<int, array<string, mixed>>
     */
    public function mapExcelToDatabase(array $rows, array $mapping, UploadBatch $batch): Collection
    {
        // Use company_id from batch to scope products
        $productsByName = Product::where('company_id', $batch->company_id)
            ->pluck('id', 'name')
            ->toArray();
        $pharmaciesCache = [];
        $productsCache = [];

        return collect($rows)->values()->map(function (array $row, int $index) use (
            $mapping,
            &$productsByName,
            &$pharmaciesCache,
            &$productsCache,
            $batch
        ) {
            $rowNumber = $index + 2;
            $raw = $this->extractRow($row, $mapping);
            $errors = [];

            $outletName = trim((string) ($raw['outlet_name'] ?? ''));
            $productName = trim((string) ($raw['product_name'] ?? ''));
            $quantity = (int) ($raw['quantity'] ?? 0);
            $soldAtRaw = $raw['sold_at'] ?? null;
            $soldAt = $this->parseDate($soldAtRaw);
            if ($soldAtRaw && ! $soldAt) {
                \Log::warning('Failed to parse date', ['row' => $rowNumber, 'raw_value' => $soldAtRaw, 'type' => gettype($soldAtRaw)]);
            }
            $unitPrice = isset($raw['unit_price']) ? (float) $raw['unit_price'] : null;
            $discount = isset($raw['discount']) ? (float) $raw['discount'] : null;

            // Skip "رصيد اول المده" rows
            if (stripos($productName, 'رصيد') !== false || stripos($productName, 'أول') !== false) {
                return [
                    'row_number' => $rowNumber,
                    'raw' => $raw,
                    'errors' => [
                        [
                            'type' => self::ERROR_VALIDATION,
                            'column' => 'product_name',
                            'message' => 'تم تجاهل صف "رصيد أول المدة".',
                        ]
                    ],
                    'product_id' => null,
                    'pharmacy_id' => null,
                    'supplier_id' => null,
                    'province_id' => null,
                    'warehouse_id' => null,
                    'quantity' => 0,
                    'sold_at' => null,
                    'unit_price' => null,
                    'discount' => null,
                    'import_hash' => null,
                    'upload_batch_id' => $batch->id,
                ];
            }

            // Skip rows with Excel formulas (starting with =)
            if (str_starts_with($productName, '=') || str_starts_with($outletName, '=') || str_starts_with((string) ($raw['sold_at'] ?? ''), '=')) {
                return [
                    'row_number' => $rowNumber,
                    'raw' => $raw,
                    'errors' => [
                        [
                            'type' => self::ERROR_VALIDATION,
                            'column' => 'product_name',
                            'message' => 'تم تجاهل صف يحتوي صيغة Excel.',
                        ]
                    ],
                    'product_id' => null,
                    'pharmacy_id' => null,
                    'supplier_id' => null,
                    'province_id' => null,
                    'warehouse_id' => null,
                    'quantity' => 0,
                    'sold_at' => null,
                    'unit_price' => null,
                    'discount' => null,
                    'import_hash' => null,
                    'upload_batch_id' => $batch->id,
                ];
            }

            // Auto-create product if not exists
            $productId = $productsByName[$productName] ?? null;
            if ($productName !== '' && ! $productId) {
                $productId = $productsCache[$productName] ?? null;
                if (! $productId) {
                    // Check for similar products (similarity search)
                    $similarProduct = $this->findSimilarProduct($productName, $batch->company_id);
                    
                    if ($similarProduct) {
                        // Log similarity warning
                        \Log::warning('Similar product detected', [
                            'batch_id' => $batch->id,
                            'row' => $rowNumber,
                            'new_name' => $productName,
                            'similar_id' => $similarProduct->id,
                            'similar_name' => $similarProduct->name,
                            'similarity' => $similarProduct['similarity'] ?? 0,
                        ]);
                        
                        // Use the similar product instead of creating new one
                        $productId = $similarProduct->id;
                        $productsCache[$productName] = $productId;
                        $productsByName[$productName] = $productId;
                    } else {
                        $product = Product::firstOrCreate(
                            [
                                'company_id' => $batch->company_id,
                                'name' => $productName,
                            ],
                            [
                                'code' => strtoupper(substr(md5($productName), 0, 8)),
                                'description' => null,
                            ]
                        );
                        $productId = $product->id;
                        $productsCache[$productName] = $productId;
                        $productsByName[$productName] = $productId;
                    }
                }
            }

            if ($quantity <= 0) {
                $errors[] = [
                    'type' => self::ERROR_VALIDATION,
                    'column' => 'quantity',
                    'message' => 'الكمية يجب أن تكون أكبر من صفر.',
                ];
            }

            if ($outletName === '') {
                $errors[] = [
                    'type' => self::ERROR_VALIDATION,
                    'column' => 'outlet_name',
                    'message' => 'اسم النقطة (المخزن/الصيدلية) مطلوب.',
                ];
            }

            if (! $soldAt) {
                $errors[] = [
                    'type' => self::ERROR_VALIDATION,
                    'column' => 'sold_at',
                    'message' => 'تاريخ البيع غير صالح.',
                ];
            }

            $productId = $productsByName[$productName] ?? null;
            $pharmacyId = null;

            if (empty($errors) && $outletName) {
                $pharmacyKey = "{$batch->supplier_id}|{$batch->province_id}|{$outletName}";
                $pharmacyId = $pharmaciesCache[$pharmacyKey] ?? null;
                if (! $pharmacyId) {
                    $pharmacy = Pharmacy::firstOrCreate(
                        [
                            'supplier_id' => $batch->supplier_id,
                            'province_id' => $batch->province_id,
                            'name' => $outletName,
                        ],
                        ['phone' => null, 'address' => null, 'warehouse_id' => null]
                    );
                    $pharmacyId = $pharmacy->id;
                    $pharmaciesCache[$pharmacyKey] = $pharmacyId;
                }
            }

            $importHash = null;

            if ($productId && isset($pharmacyId) && $soldAt) {
                $importHash = $this->buildImportHash(
                    (int) $productId,
                    (int) $pharmacyId,
                    $soldAt
                );
            }

            return [
                'row_number' => $rowNumber,
                'raw' => $raw,
                'errors' => $errors,
                'product_id' => $productId,
                'pharmacy_id' => $pharmacyId ?? null,
                'supplier_id' => $batch->supplier_id,
                'province_id' => $batch->province_id,
                'warehouse_id' => null,
                'quantity' => $quantity,
                'sold_at' => $soldAt,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'import_hash' => $importHash,
                'upload_batch_id' => $batch->id,
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    public function detectDuplicates(Collection $rows): array
    {
        // Allow duplicates - return empty array to skip duplicate checking
        // This allows multiple sales for same product+pharmacy+date
        return [];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function bulkInsert(Collection $rows, UploadBatch $batch): int
    {
        $inserted = 0;
        $chunkSize = (int) config('sale_import.chunk_size', 500);

        $rows->chunk($chunkSize)->each(function (Collection $chunk) use ($batch, &$inserted) {
            $payload = $chunk->map(fn (array $row) => [
                'product_id' => $row['product_id'],
                'pharmacy_id' => $row['pharmacy_id'],
                'supplier_id' => $row['supplier_id'],
                'province_id' => $row['province_id'],
                'warehouse_id' => null,
                'upload_batch_id' => $batch->id,
                'quantity' => $row['quantity'],
                'sold_at' => $row['sold_at'],
                'unit_price' => $row['unit_price'],
                'discount' => $row['discount'],
                'import_hash' => $row['import_hash'],
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if (! empty($payload)) {
                Sale::insert($payload);
                $inserted += count($payload);
            }
        });

        return $inserted;
    }

    public function generateErrorReport(UploadBatch $batch): string
    {
        $errors = $batch->errors()->orderBy('row_number')->get();
        $lines = ['row_number,column,error_type,message'];

        foreach ($errors as $error) {
            $lines[] = implode(',', [
                $error->row_number,
                $error->column ?? '',
                $error->error_type,
                '"'.str_replace('"', '""', $error->message).'"',
            ]);
        }

        $path = "imports/reports/batch-{$batch->id}-errors.csv";
        Storage::disk('local')->put($path, implode("\n", $lines));

        return $path;
    }

    private function failBatch(UploadBatch $batch, string $message): SaleImportResult
    {
        $batch->update([
            'status' => UploadBatchStatus::Failed,
            'completed_at' => now(),
            'notes' => $message,
        ]);

        return new SaleImportResult(
            batch: $batch->fresh(),
            success: false,
            message: $message,
        );
    }

    private function normalizeHeader(string $header): string
    {
        return Str::lower(trim(str_replace([' ', '-'], '_', $header)));
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $mapping
     * @return array<string, mixed>
     */
    private function extractRow(array $row, array $mapping): array
    {
        $extracted = [];

        foreach ($mapping as $key => $index) {
            $extracted[$key] = $row[$index] ?? null;
        }

        return $extracted;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        try {
            // Try d/m/Y format first (common in Arabic Excel files)
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', (string) $value)) {
                return Carbon::createFromFormat('d/m/Y', (string) $value)->format('Y-m-d');
            }
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildImportHash(int $productId, int $pharmacyId, string $soldAt): string
    {
        // Duplicate detection: same product + same pharmacy + same date
        return hash('sha256', "{$productId}|{$pharmacyId}|{$soldAt}");
    }

    /**
     * Find similar product by name using Levenshtein distance
     * Returns product object with similarity score if found, null otherwise
     */
    private function findSimilarProduct(string $productName, int $companyId): ?Product
    {
        $threshold = 0.85; // 85% similarity threshold
        $existingProducts = Product::where('company_id', $companyId)
            ->get()
            ->map(function ($product) use ($productName) {
                $similarity = $this->calculateSimilarity($productName, $product->name);
                return [
                    'product' => $product,
                    'similarity' => $similarity,
                ];
            })
            ->filter(fn ($item) => $item['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->first();

        return $existingProducts ? $existingProducts['product'] : null;
    }

    /**
     * Calculate similarity between two strings using similar_text
     * Returns value between 0 and 1
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Normalize strings: remove extra spaces, convert to lowercase for comparison
        $str1 = $this->normalizeForComparison($str1);
        $str2 = $this->normalizeForComparison($str2);

        if ($str1 === $str2) {
            return 1.0;
        }

        if (empty($str1) || empty($str2)) {
            return 0.0;
        }

        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }

    /**
     * Normalize string for comparison
     * Removes extra spaces, standardizes Arabic characters
     */
    private function normalizeForComparison(string $str): string
    {
        // Remove extra spaces
        $str = preg_replace('/\s+/', ' ', trim($str));
        
        // Standardize Arabic characters (أ -> ا, ة -> ه)
        $str = str_replace(['أ', 'إ', 'آ'], 'ا', $str);
        $str = str_replace('ة', 'ه', $str);
        
        // Remove common variations in concentration notation
        $str = preg_replace('/\s*(مجم|مل|كبسولة|قرص|نقط|أمبول)\s*/i', '', $str);
        $str = preg_replace('/\s*\d+\s*(\/|÷)\s*\d+\s*/', '', $str); // Remove fractions like 400/5
        
        return $str;
    }
}
