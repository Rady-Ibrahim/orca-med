<?php

namespace App\Services;

use App\DTOs\SaleImportResult;
use App\Enums\UploadBatchStatus;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\Province;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\UploadBatch;
use App\Models\UploadBatchError;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SaleImportService
{
    public const ERROR_VALIDATION = 'validation';

    public const ERROR_NOT_FOUND = 'not_found';

    public const ERROR_DUPLICATE = 'duplicate';

    public const ERROR_AMBIGUOUS_PRODUCT = 'ambiguous_product';

    private const BASE_AMBIGUOUS_THRESHOLD = 0.90;

    private const FINGERPRINT_REVIEW_THRESHOLD = 0.60;

    private const AUTO_MATCH_THRESHOLD = 0.95;

    public function __construct() {}

    public function createQueuedBatch(
        UploadedFile $file,
        User $user,
        int $companyId,
        int $supplierId,
        int $provinceId,
        ?int $warehouseId = null
    ): UploadBatch {
        $storedPath = $file->store('imports/sales', 'local');

        return UploadBatch::create([
            'uploaded_by' => $user->id,
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'province_id' => $provinceId,
            'warehouse_id' => $warehouseId,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => UploadBatchStatus::Queued,
        ]);
    }

    /**
     * Detect similar products in the uploaded file before processing
     * Returns array of similar product names for reconciliation
     */
    public function detectSimilarProductsInFile(string $storedPath, int $companyId): array
    {
        \Log::info('detectSimilarProductsInFile started', ['stored_path' => $storedPath, 'company_id' => $companyId]);

        $fullPath = Storage::disk('local')->path($storedPath);
        if (! is_readable($fullPath)) {
            \Log::error('File not readable', ['full_path' => $fullPath]);
            return [];
        }

        $rows = Excel::toArray([], $fullPath)[0] ?? [];
        if (empty($rows)) {
            \Log::error('File is empty');
            return [];
        }

        $headerRow = array_shift($rows);
        \Log::info('Similarity header row', ['header' => $headerRow]);

        $mapping = $this->validateExcelSchema($headerRow);
        \Log::info('Similarity mapping result', ['mapping' => $mapping]);

        if ($mapping === null) {
            \Log::error('Validation failed in similarity detection');
            return [];
        }

        // Extract product names, their row numbers, and prices from rows
        $productOccurrences = [];  // productName => [rowNumber, ...]
        $productPrices = [];       // productName => float|null (first seen price)
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $raw = $this->extractRow($row, $mapping);
            $productName = $raw['product_name'] ?? null;

            if (! $productName) {
                continue;
            }

            $rowNumber = $index + 2; // +2 to account for header row (Excel indexing)

            $productOccurrences[$productName] ??= [];
            $productOccurrences[$productName][] = $rowNumber;

            // Store the first seen unit_price for this product name
            if (! isset($productPrices[$productName])) {
                $unitPrice = isset($raw['unit_price']) && $raw['unit_price'] !== null && $raw['unit_price'] !== ''
                    ? (float) $raw['unit_price']
                    : null;
                $productPrices[$productName] = $unitPrice;
            }
        }

        \Log::info('Product names extracted', ['count' => count($productOccurrences)]);

        // Detect similarities using strict first-token logic
        $similarities = [];
        foreach ($productOccurrences as $productName => $rowsForProduct) {
            $matchResult = $this->findProductMatch($productName, $companyId, null);

            if (empty($matchResult['should_intercept'])) {
                continue;
            }

            $candidates = collect($matchResult['candidates'] ?? []);
            $candidateIds = $candidates
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $candidateProducts = empty($candidateIds)
                ? collect()
                : Product::whereIn('id', $candidateIds)->get()->keyBy('id');

            $incomingPrice = $productPrices[$productName] ?? null;

            $similarEntries = $candidates->map(function (array $candidate) use ($candidateProducts) {
                $productId = $candidate['product_id'] ?? null;
                $product = $productId ? $candidateProducts->get($productId) : null;

                if (! $product && ! empty($candidate['product_name'])) {
                    $product = (object) [
                        'id' => $productId,
                        'name' => $candidate['product_name'],
                        'code' => null,
                        'price' => null,
                    ];
                }

                if (! $product) {
                    return null;
                }

                return [
                    'product' => $product,
                    'similarity' => (($candidate['similarity'] ?? 0) / 100),
                ];
            })->filter()->values()->all();

            $similarities[$productName] = [
                'original' => $productName,
                'incoming_price' => $incomingPrice,
                'rows' => array_values(array_unique($rowsForProduct)),
                'similar' => $similarEntries,
            ];
        }

        $similarities = $this->detectWithinFileDuplicates($productOccurrences, $productPrices, $similarities);

        \Log::info('Similarities detected', ['count' => count($similarities)]);

        return $similarities;
    }

    /**
     * @param  array<string, array<int>>  $productOccurrences
     * @param  array<string, float|null>  $productPrices
     * @param  array<string, array<string, mixed>>  $similarities
     * @return array<string, array<string, mixed>>
     */
    private function detectWithinFileDuplicates(array $productOccurrences, array $productPrices, array $similarities): array
    {
        $byFingerprint = [];

        foreach (array_keys($productOccurrences) as $productName) {
            $fingerprint = $this->extractProductMetadata($productName)['fingerprint'];
            if ($fingerprint === '||' || $fingerprint === '|unknown|') {
                continue;
            }
            $byFingerprint[$fingerprint][] = $productName;
        }

        foreach ($byFingerprint as $names) {
            if (count($names) < 2) {
                continue;
            }

            usort($names, fn ($a, $b) => count($productOccurrences[$b]) <=> count($productOccurrences[$a]));

            $canonicalName = $names[0];
            $canonicalPrice = $productPrices[$canonicalName] ?? null;

            for ($i = 1; $i < count($names); $i++) {
                $variantName = $names[$i];
                $variantPrice = $productPrices[$variantName] ?? null;

                // If both products have prices and they are different, they are distinct products — skip
                if ($canonicalPrice !== null && $variantPrice !== null && abs($canonicalPrice - $variantPrice) > 0.01) {
                    continue;
                }

                if (isset($similarities[$variantName])) {
                    continue;
                }

                $similarities[$variantName] = [
                    'original' => $variantName,
                    'incoming_price' => $variantPrice,
                    'rows' => array_values(array_unique($productOccurrences[$variantName])),
                    'similar' => [[
                        'product' => (object) [
                            'id' => null,
                            'name' => $canonicalName,
                            'code' => null,
                            'price' => $canonicalPrice,
                        ],
                        'similarity' => $this->calculateSimilarity($variantName, $canonicalName),
                    ]],
                    'within_file' => true,
                ];
            }
        }

        return $similarities;
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
            \Log::info('Import mapping result', ['batch_id' => $batch->id, 'mapping' => $mapping]);

            if ($mapping === null) {
                return $this->failBatch($batch, 'تنسيق الأعمدة غير صحيح. راجع ملف القالب المتوقع.');
            }

            $batch->update(['total_rows' => count($rows)]);

            $mappedRows = $this->mapExcelToDatabase($rows, $mapping, $batch);
            $validRows = $mappedRows->filter(fn (array $row) => empty($row['errors']));
            $invalidRows = $mappedRows->filter(fn (array $row) => ! empty($row['errors']));

            foreach ($invalidRows as $invalid) {
                foreach ($invalid['errors'] as $error) {
                    $rowData = [
                        'raw' => $invalid['raw'],
                        'mapped' => Arr::except($invalid, ['raw', 'errors']),
                    ];

                    if (! empty($error['candidates'] ?? [])) {
                        $rowData['candidates'] = $error['candidates'];
                    }

                    UploadBatchError::create([
                        'upload_batch_id' => $batch->id,
                        'row_number' => $invalid['row_number'],
                        'column' => $error['column'] ?? null,
                        'error_type' => $error['type'],
                        'message' => $error['message'],
                        'row_data' => $rowData,
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
                    'row_data' => [
                        'raw' => $dup['raw'],
                        'mapped' => Arr::except($dup, ['raw', 'errors'])
                    ],
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
                // Skip missing fields - make all fields optional
                continue;
            }

            $mapping[$canonical] = $index;
        }

        foreach (config('sale_import.required_fields', []) as $required) {
            if (! isset($mapping[$required])) {
                \Log::warning('Required import column missing', ['field' => $required, 'header' => $headerRow, 'mapping' => $mapping]);

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
        \Log::info('mapExcelToDatabase started', ['batch_id' => $batch->id, 'total_rows' => count($rows)]);

        // Use company_id from batch to scope products
        $productsByName = Product::where('company_id', $batch->company_id)
            ->pluck('id', 'name')
            ->toArray();
        $pharmaciesCache = [];
        $productsCache = [];

        $nameMappings = $this->getBatchNameMappings($batch);

        return collect($rows)->values()->map(function (array $row, int $index) use (
            $mapping,
            &$productsByName,
            &$pharmaciesCache,
            &$productsCache,
            $batch,
            $nameMappings
        ) {
            $rowNumber = $index + 2;
            $raw = $this->extractRow($row, $mapping);
            $errors = [];

            $outletName = trim((string) ($raw['outlet_name'] ?? ''));
            $originalProductName = trim((string) ($raw['product_name'] ?? ''));
            $productName = $nameMappings[$originalProductName] ?? $originalProductName;
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

            // STRICT PRODUCT MATCHING WITH INTERCEPTION
            $productId = $productsByName[$productName] ?? null;
            if ($productName !== '' && ! $productId) {
                $productId = $productsCache[$productName] ?? null;
                if (! $productId) {
                    // Use strict matching with interception rules
                    $matchResult = $this->findProductMatch($productName, $batch->company_id, $batch->id);
                    
                    if ($matchResult['should_intercept']) {
                        // INTERCEPT: Typo/spelling mismatch detected
                        // Flag row for manual review via UI
                        $errors[] = [
                            'type' => self::ERROR_AMBIGUOUS_PRODUCT,
                            'column' => 'product_name',
                            'message' => 'اسم المنتج يحتاج إلى تصحيح يدوي. يوجد منتجات مشابهة في النظام.',
                            'candidates' => $matchResult['candidates'] ?? [],
                        ];
                        
                        \Log::warning('Product ambiguity intercepted', [
                            'batch_id' => $batch->id,
                            'row' => $rowNumber,
                            'product_name' => $productName,
                            'candidates' => $matchResult['candidates'] ?? [],
                        ]);
                        
                        // Don't process this row - it needs manual review
                        $productId = null;
                    } elseif ($matchResult['type'] === 'exact') {
                        // Rule A: Exact match found
                        $productId = $matchResult['product']->id;
                        $productsCache[$productName] = $productId;
                        $productsByName[$productName] = $productId;
                        
                        \Log::info('Product exact match found', [
                            'batch_id' => $batch->id,
                            'row' => $rowNumber,
                            'product_name' => $productName,
                            'product_id' => $productId,
                        ]);
                    } elseif ($matchResult['type'] === 'strength_variant') {
                        // Rule B: Different strength - auto-create as new variant
                        $product = $this->createProductForBatch($batch, $productName, $unitPrice);
                        $productId = $product->id;
                        $productsCache[$productName] = $productId;
                        $productsByName[$productName] = $productId;
                        
                        \Log::info('Product strength variant created', [
                            'batch_id' => $batch->id,
                            'row' => $rowNumber,
                            'product_name' => $productName,
                            'product_id' => $productId,
                        ]);
                    } else {
                        // Type 'new': No match found, create new product
                        $product = $this->createProductForBatch($batch, $productName, $unitPrice);
                        $productId = $product->id;
                        $productsCache[$productName] = $productId;
                        $productsByName[$productName] = $productId;
                        
                        \Log::info('New product created', [
                            'batch_id' => $batch->id,
                            'row' => $rowNumber,
                            'product_name' => $productName,
                            'product_id' => $productId,
                        ]);
                    }

                    if ($productId && $originalProductName !== '' && $originalProductName !== $productName) {
                        ProductAlias::updateOrCreate(
                            ['alias_name' => $originalProductName],
                            ['product_id' => $productId]
                        );
                    }
                }
            }

            if (isset($mapping['quantity']) && $quantity <= 0) {
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

            // Use today's date if sold_at is not provided
            if (! $soldAt) {
                $soldAt = now()->format('Y-m-d');
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
                        [
                            'upload_batch_id' => $batch->id,
                            'phone' => null,
                            'address' => null,
                            'warehouse_id' => $batch->warehouse_id
                        ]
                    );
                    if ($batch->warehouse_id && $pharmacy->warehouse_id !== $batch->warehouse_id) {
                        $pharmacy->update(['warehouse_id' => $batch->warehouse_id]);
                    }
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
                'warehouse_id' => $batch->warehouse_id,
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
                'warehouse_id' => $row['warehouse_id'] ?? $batch->warehouse_id,
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
        \Log::error('failBatch called', ['batch_id' => $batch->id, 'message' => $message]);

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
        // Remove zero-width and non-breaking spaces
        $header = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{FEFF}]/u', ' ', $header);
        $header = trim($header);

        // Normalize Arabic characters (same logic as normalizeForComparison)
        $header = str_replace(['أ', 'إ', 'آ'], 'ا', $header);
        $header = str_replace('ة', 'ه', $header);
        $header = str_replace('ى', 'ي', $header);   // ← fixes "الصيدلى" → "الصيدلي"
        $header = str_replace(['ؤ', 'ئ'], 'و', $header);

        // Collapse spaces/dashes to underscore
        $header = preg_replace('/[\s\-]+/u', '_', $header);
        $header = trim($header, '_-');

        return Str::lower($header);
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

    private function createProductForBatch(UploadBatch $batch, string $productName, ?float $unitPrice = null): Product
    {
        return Product::create([
            'company_id' => $batch->company_id,
            'upload_batch_id' => $batch->id,
            'name' => $productName,
            'code' => strtoupper(substr(md5($batch->company_id . $productName), 0, 8)),
            'price' => $unitPrice > 0 ? $unitPrice : 0,
        ]);
    }

    /**
     * Extract product metadata for matching: brand, dose (mg/ml), and form group.
     */
    private function extractProductMetadata(string $productName): array
    {
        $dose = $this->extractDose($productName);
        $form = $this->detectProductForm($productName);
        $formGroup = $this->normalizeFormGroup($form);
        $brand = $this->extractBrand($productName);

        return [
            'full_name' => $productName,
            'base_name' => $brand,
            'brand' => $brand,
            'dose' => $dose,
            'form' => $form,
            'form_group' => $formGroup,
            'fingerprint' => $this->buildProductFingerprint($brand, $dose, $formGroup),
            'strengths' => $dose !== null ? [$dose] : [],
            'unit' => null,
            'has_strength' => $dose !== null,
        ];
    }

    private function extractDose(string $productName): ?string
    {
        // Only extract actual therapeutic doses (mg-based) — NOT pack sizes like "120 مل"
        $mgPatterns = [
            '/(\d+(?:\.\d+)?)\s*(?:مجم|ملجم|ملغ|mg)\b/iu',
            '/(\d+(?:\.\d+)?)(?:مجم|ملجم|ملغ|mg)\b/iu',
        ];

        foreach ($mgPatterns as $pattern) {
            if (preg_match($pattern, $productName, $matches)) {
                return $matches[1];
            }
        }

        // Vial numbers are dose-like (e.g. "40 فيال")
        if (preg_match('/(\d+(?:\.\d+)?)(?:فيال|فيل|vial)/iu', $productName, $matches)) {
            return $matches[1];
        }

        // مل / ml alone is a pack size, NOT a dose — do not use it as dose
        return null;
    }

    private function extractBrand(string $productName): string
    {
        if (preg_match('/^([\p{Arabic}]+)/u', trim($productName), $matches)) {
            return $this->normalizeForComparison($matches[1]);
        }

        $tokens = $this->tokenizeBaseName($productName);

        return $tokens[0] ?? $this->normalizeForComparison($productName);
    }

    private function detectProductForm(string $productName): string
    {
        if (preg_match('/فيال|فيل|vial|امبول|أمبول|ampoule/iu', $productName)) {
            return 'vial';
        }
        if (preg_match('/كبسول|capsule|\bcap\b/iu', $productName)) {
            return 'capsule';
        }
        if (preg_match('/شريط|strip/iu', $productName)) {
            return 'strip';
        }
        if (preg_match('/قرص|tablet|\btab\b/iu', $productName)) {
            return 'tablet';
        }
        if (preg_match('/شراب|syrup/iu', $productName)) {
            return 'syrup';
        }

        return 'unknown';
    }

    private function normalizeFormGroup(string $form): string
    {
        return in_array($form, ['vial'], true) ? 'parenteral' : 'oral';
    }

    private function buildProductFingerprint(string $brand, ?string $dose, string $formGroup): string
    {
        return mb_strtolower(trim($brand), 'UTF-8') . '|' . ($dose ?? '') . '|' . $formGroup;
    }

    /**
     * @return array<string, string>
     */
    private function getBatchNameMappings(UploadBatch $batch): array
    {
        if (! $batch->notes) {
            return [];
        }

        $decoded = json_decode($batch->notes, true);

        return is_array($decoded) ? ($decoded['name_mappings'] ?? []) : [];
    }

    /**
     * @param  array<string, string>  $nameMappings
     */
    public function saveBatchNameMappings(UploadBatch $batch, array $nameMappings): void
    {
        if (empty($nameMappings)) {
            return;
        }

        $decoded = json_decode($batch->notes ?? '{}', true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $decoded['name_mappings'] = array_merge($decoded['name_mappings'] ?? [], $nameMappings);
        $batch->update(['notes' => json_encode($decoded, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Match imported product names to existing catalog entries.
     *
     * Uses brand + dose + form fingerprint so spelling/pack-size variants
     * route to reconciliation instead of creating duplicate products.
     */
    private function findProductMatch(string $productName, int $companyId, ?int $currentBatchId = null): array
    {
        $incomingMeta = $this->extractProductMetadata($productName);

        if ($productName !== '') {
            $alias = ProductAlias::with('product')
                ->where('alias_name', $productName)
                ->first();

            if ($alias && $alias->product && $alias->product->company_id === $companyId) {
                return [
                    'type' => 'exact',
                    'product' => $alias->product,
                    'should_intercept' => false,
                ];
            }
        }

        $exactMatch = Product::where('company_id', $companyId)
            ->where('name', $productName)
            ->first();

        if ($exactMatch) {
            return [
                'type' => 'exact',
                'product' => $exactMatch,
                'should_intercept' => false,
            ];
        }

        $allProducts = Product::where('company_id', $companyId)->get();

        if ($allProducts->isEmpty()) {
            return [
                'type' => 'new',
                'should_intercept' => false,
            ];
        }

        $scored = $allProducts->map(function (Product $product) use ($incomingMeta, $productName) {
            $dbMeta = $this->extractProductMetadata($product->name);

            return [
                'product' => $product,
                'db_meta' => $dbMeta,
                'name_similarity' => $this->calculateSimilarity($productName, $product->name),
                'same_fingerprint' => $incomingMeta['fingerprint'] === $dbMeta['fingerprint'],
                'same_brand_dose' => $incomingMeta['brand'] !== ''
                    && $incomingMeta['brand'] === $dbMeta['brand']
                    && $incomingMeta['dose'] !== null
                    && $incomingMeta['dose'] === $dbMeta['dose'],
            ];
        });

        $fingerprintMatches = $scored
            ->filter(fn (array $item) => $item['same_fingerprint'])
            ->sortByDesc('name_similarity')
            ->values();

        if ($fingerprintMatches->isNotEmpty()) {
            return $this->resolveFingerprintMatches($fingerprintMatches, $productName);
        }

        $brandDoseMatches = $scored
            ->filter(fn (array $item) => $item['same_brand_dose'])
            ->sortByDesc('name_similarity')
            ->values();

        if ($brandDoseMatches->isNotEmpty()) {
            $best = $brandDoseMatches->first();

            if ($best['name_similarity'] >= self::FINGERPRINT_REVIEW_THRESHOLD) {
                return $this->buildAmbiguousMatch($brandDoseMatches);
            }

            if ($incomingMeta['form_group'] !== $best['db_meta']['form_group']) {
                return [
                    'type' => 'strength_variant',
                    'should_intercept' => false,
                ];
            }
        }

        $sameBrandMatches = $scored
            ->filter(fn (array $item) => $incomingMeta['brand'] !== '' && $incomingMeta['brand'] === $item['db_meta']['brand'])
            ->sortByDesc('name_similarity')
            ->values();

        if ($sameBrandMatches->isNotEmpty()) {
            $best = $sameBrandMatches->first();

            // Same brand + same form group → likely same product with different pack size or
            // extra words in name → send to reconciliation so user can decide
            if (
                $incomingMeta['dose'] === null
                && $best['db_meta']['dose'] === null
                && $incomingMeta['form_group'] === $best['db_meta']['form_group']
                && $best['name_similarity'] >= self::FINGERPRINT_REVIEW_THRESHOLD
            ) {
                return $this->buildAmbiguousMatch($sameBrandMatches);
            }

            // Same brand, incoming has a dose that doesn't exist yet → new strength variant
            if ($incomingMeta['dose'] !== null) {
                $hasSameDose = $sameBrandMatches->contains(
                    fn (array $item) => $item['db_meta']['dose'] === $incomingMeta['dose']
                );

                if (! $hasSameDose) {
                    return [
                        'type' => 'strength_variant',
                        'should_intercept' => false,
                    ];
                }
            }
        }

        $brandTypoMatches = $scored
            ->filter(function (array $item) use ($incomingMeta) {
                if ($incomingMeta['brand'] === '' || $item['db_meta']['brand'] === '') {
                    return false;
                }

                if ($incomingMeta['brand'] === $item['db_meta']['brand']) {
                    return false;
                }

                return $this->calculateSimilarity($incomingMeta['brand'], $item['db_meta']['brand'])
                    >= self::BASE_AMBIGUOUS_THRESHOLD;
            })
            ->sortByDesc('name_similarity')
            ->values();

        if ($brandTypoMatches->isNotEmpty()) {
            return $this->buildAmbiguousMatch($brandTypoMatches);
        }

        return [
            'type' => 'new',
            'should_intercept' => false,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @return array<string, mixed>
     */
    private function resolveFingerprintMatches(Collection $matches, string $productName): array
    {
        $best = $matches->first();

        if ($best['name_similarity'] >= self::AUTO_MATCH_THRESHOLD || $best['product']->name === $productName) {
            return [
                'type' => 'exact',
                'product' => $best['product'],
                'should_intercept' => false,
            ];
        }

        return $this->buildAmbiguousMatch($matches);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @return array<string, mixed>
     */
    private function buildAmbiguousMatch(Collection $matches): array
    {
        $candidates = $matches
            ->take(5)
            ->map(fn (array $item) => [
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'similarity' => round($item['name_similarity'] * 100, 1),
            ])
            ->values()
            ->all();

        return [
            'type' => 'ambiguous',
            'candidates' => $candidates,
            'should_intercept' => true,
        ];
    }

    /**
     * Calculate similarity between base names (without strength)
     * Uses normalized comparison
     */
    private function calculateBaseSimilarity(string $baseName1, string $baseName2): float
    {
        $norm1 = $this->normalizeForComparison($baseName1);
        $norm2 = $this->normalizeForComparison($baseName2);
        
        if ($norm1 === $norm2) {
            return 1.0;
        }
        
        if (empty($norm1) || empty($norm2)) {
            return 0.0;
        }
        
        similar_text($norm1, $norm2, $percent);
        return $percent / 100;
    }

    /**
     * Normalize strength array for comparison
     * Converts ["500"] to "500" for consistent comparison
     */
    private function normalizeStrengths(array $strengths): string
    {
        if (empty($strengths)) {
            return '';
        }
        return implode('|', array_map('trim', $strengths));
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
        // Remove extra spaces and trim first
        $str = preg_replace('/\s+/', ' ', trim($str));

        // Standardize Arabic characters (أ -> ا, ة -> ه, ى -> ي)
        $str = str_replace(['أ', 'إ', 'آ'], 'ا', $str);
        $str = str_replace('ة', 'ه', $str);
        $str = str_replace('ى', 'ي', $str);
        $str = str_replace(['ؤ', 'ئ'], 'و', $str);

        // Collapse Arabic tatweel/kashida (e.g. شـــراب → شراب)
        $str = preg_replace('/ـ+/u', '', $str);

        // Remove punctuation and separators
        $str = str_replace(['%', '.', ',', '-', '_', '(', ')'], ' ', $str);

        // Remove pack-size units (مل, ml) — these are NOT part of the product identity
        $str = preg_replace('/\s*\d+\s*(?:مل|ml)\b\s*/iu', ' ', $str);

        // Remove fractions like 400/5
        $str = preg_replace('/\s*\d+\s*(\/|÷)\s*\d+\s*/u', ' ', $str);

        // Remove digits
        $str = preg_replace('/[0-9٠-٩]+/u', ' ', $str);

        // Collapse multiple spaces
        $str = preg_replace('/\s+/', ' ', trim($str));

        return $str;
    }

    private function tokenizeBaseName(string $baseName): array
    {
        $normalized = $this->normalizeForComparison($baseName);

        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized);

        return array_values(array_filter($tokens, fn ($token) => $token !== ''));
    }
}
