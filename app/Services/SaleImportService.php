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

        // Extract product names and their row numbers from rows
        $productOccurrences = [];
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

            $similarEntries = $candidates->map(function (array $candidate) use ($candidateProducts) {
                $productId = $candidate['product_id'] ?? null;
                $product = $productId ? $candidateProducts->get($productId) : null;

                if (! $product && ! empty($candidate['product_name'])) {
                    $product = (object) [
                        'id' => $productId,
                        'name' => $candidate['product_name'],
                        'code' => $product?->code ?? null,
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
                'rows' => array_values(array_unique($rowsForProduct)),
                'similar' => $similarEntries,
            ];
        }

        \Log::info('Similarities detected', ['count' => count($similarities)]);

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
        $header = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{FEFF}]/u', ' ', $header);
        $header = trim($header);
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
     * Extract product metadata: base name and strength/grammage values
     * Examples:
     *   "أسبرين 500 ملغ" → { base_name: "أسبرين", strengths: ["500"], unit: "ملغ" }
     *   "جافيسكون 10 مل" → { base_name: "جافيسكون", strengths: ["10"], unit: "مل" }
     *   "باراسيتامول" → { base_name: "باراسيتامول", strengths: [], unit: null }
     */
    private function extractProductMetadata(string $productName): array
    {
        // Pattern to match: number + optional unit
        // Supports: "500 ملغ", "1000mg", "10g", "250", "5 مل", etc.
        $pattern = '/(\d+(?:\.\d+)?)\s*([ملغmgmlgكبسولةقرصنقطأمبولcapsuletabletdropampule]*)/iu';
        
        preg_match_all($pattern, $productName, $matches);
        
        $strengths = [];
        $unit = null;
        
        if (!empty($matches[1])) {
            $strengths = array_map('trim', $matches[1]);
            // Get the unit from the last match
            if (!empty($matches[2])) {
                $unit = trim($matches[2][count($matches[2]) - 1]) ?: null;
            }
        }
        
        // Remove all numbers and units to get base name
        $baseName = preg_replace('/\d+\s*[ملغmgmlgكبسولةقرصنقطأمبولcapsuletabletdropampule]*/iu', '', $productName);
        $baseName = preg_replace('/\s+/', ' ', trim($baseName));
        
        return [
            'full_name' => $productName,
            'base_name' => $baseName,
            'strengths' => $strengths,
            'unit' => $unit,
            'has_strength' => !empty($strengths),
        ];
    }

    /**
     * STRICT PRODUCT MATCHING WITH MANUAL INTERCEPTION
     *
     * Rules:
     * A) Exact Match (100%): Process immediately
     * B) Different Strengths: Auto-create as new product variant
     * C) Spelling Typos/Mismatches: INTERCEPT and route to manual review
     *
     * Returns array with keys:
     *   - 'type': 'exact' | 'strength_variant' | 'ambiguous' | 'new'
     *   - 'product': Product model (if exact or strength_variant)
     *   - 'candidates': array of potential matches (if ambiguous)
     *   - 'should_intercept': boolean (true if needs manual review)
     */
    private function findProductMatch(string $productName, int $companyId, ?int $currentBatchId = null): array
    {
        $incomingMeta = $this->extractProductMetadata($productName);

        $alias = null;
        if ($productName !== '') {
            $alias = ProductAlias::with('product')
                ->where('alias_name', $productName)
                ->first();
        }

        if ($alias && $alias->product && $alias->product->company_id === $companyId) {
            return [
                'type' => 'exact',
                'product' => $alias->product,
                'should_intercept' => false,
            ];
        }

        // Rule A: Check for exact match (100%)
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
        
        // Get all products for this company, excluding those from current batch
        $productQuery = Product::where('company_id', $companyId);
        if ($currentBatchId !== null) {
            $productQuery->where('upload_batch_id', '!=', $currentBatchId);
        }
        $allProducts = $productQuery->get();
        
        if ($allProducts->isEmpty()) {
            // No products exist yet, create new
            return [
                'type' => 'new',
                'should_intercept' => false,
            ];
        }
        
        // Analyze each existing product
        $matches = $allProducts->map(function ($product) use ($incomingMeta) {
            $dbMeta = $this->extractProductMetadata($product->name);
            
            // Compare base names (without strength)
            $baseSimilarity = $this->calculateBaseSimilarity(
                $incomingMeta['base_name'],
                $dbMeta['base_name']
            );
            
            return [
                'product' => $product,
                'db_meta' => $dbMeta,
                'base_similarity' => $baseSimilarity,
            ];
        })->toArray();
        
        // Rule B: Check for strength variants (same base name, different strength)
        $normalizedIncomingBase = $this->normalizeForComparison($incomingMeta['base_name']);
        $incomingTokens = $this->tokenizeBaseName($incomingMeta['base_name']);

        $sameBaseMatches = array_filter($matches, function ($item) use ($incomingTokens) {
            $dbTokens = $this->tokenizeBaseName($item['db_meta']['base_name']);

            return ! empty($incomingTokens)
                && ! empty($dbTokens)
                && $incomingTokens[0] === $dbTokens[0];
        });

        if (! empty($sameBaseMatches)) {
            $incomingStrengths = $this->normalizeStrengths($incomingMeta['strengths']);

            $sameStrengthMatches = array_filter($sameBaseMatches, function ($item) use ($incomingStrengths) {
                $dbStrengths = $this->normalizeStrengths($item['db_meta']['strengths']);
                return $incomingStrengths !== '' && $incomingStrengths === $dbStrengths;
            });

            if (! empty($sameStrengthMatches)) {
                $firstMatch = reset($sameStrengthMatches);
                return [
                    'type' => 'exact',
                    'product' => $firstMatch['product'],
                    'should_intercept' => false,
                ];
            }

            return [
                'type' => 'strength_variant',
                'should_intercept' => false,
            ];
        }

        // Rule C: Check for spelling typos/mismatches
        // If base similarity is high (≥60%) but base names differ, it's likely a typo
        $potentialMatches = array_filter($matches, function ($item) use ($incomingMeta) {
            // Skip if full names are 100% identical (should have been caught by Rule A)
            if ($incomingMeta['full_name'] === $item['db_meta']['full_name']) {
                return false;
            }

            $dbTokens = $this->tokenizeBaseName($item['db_meta']['base_name']);
            $incomingTokens = $this->tokenizeBaseName($incomingMeta['base_name']);

            if (empty($incomingTokens) || empty($dbTokens)) {
                return false;
            }

            $firstTokenMatches = $incomingTokens[0] === $dbTokens[0];
            if ($firstTokenMatches) {
                return false;
            }

            $firstTokenSimilarity = $this->calculateSimilarity($incomingTokens[0], $dbTokens[0]);

            return $firstTokenSimilarity >= self::BASE_AMBIGUOUS_THRESHOLD;
        });
        
        if (!empty($potentialMatches)) {
            // INTERCEPT: Route to manual review
            // Sort by similarity descending
            usort($potentialMatches, function ($a, $b) {
                return $b['base_similarity'] <=> $a['base_similarity'];
            });
            
            $candidates = array_map(function ($item) {
                return [
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'similarity' => round($item['base_similarity'] * 100, 1),
                ];
            }, $potentialMatches);
            
            return [
                'type' => 'ambiguous',
                'candidates' => $candidates,
                'should_intercept' => true,
            ];
        }
        
        // No match found, create new product
        return [
            'type' => 'new',
            'should_intercept' => false,
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

        // Remove punctuation that can stick words together
        $str = str_replace(['%', '.', ',', '-', '_', '(', ')'], ' ', $str);

        // Replace measurement/unit words with a space
        $str = preg_replace('/\s*(مجم|مل|كبسولة|قرص|نقط|أمبول)\s*/iu', ' ', $str);

        // Remove fractions like 400/5 but keep space separation
        $str = preg_replace('/\s*\d+\s*(\/|÷)\s*\d+\s*/u', ' ', $str);

        // Remove any remaining digits (western or arabic) and symbols, replace with space
        $str = preg_replace('/[0-9٠-٩]+/u', ' ', $str);

        // Collapse multiple spaces again after replacements
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
