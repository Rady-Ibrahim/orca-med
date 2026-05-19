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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SaleImportService
{
    private const ERROR_SCHEMA = 'schema';

    private const ERROR_VALIDATION = 'validation';

    private const ERROR_NOT_FOUND = 'not_found';

    private const ERROR_DUPLICATE = 'duplicate';

    public function process(UploadedFile $file, User $user): SaleImportResult
    {
        $storedPath = $file->store('imports/sales', 'local');

        $batch = UploadBatch::create([
            'uploaded_by' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => UploadBatchStatus::Processing,
            'started_at' => now(),
        ]);

        try {
            $rows = Excel::toArray([], Storage::disk('local')->path($storedPath))[0] ?? [];

            if (empty($rows)) {
                return $this->failBatch($batch, 'الملف فارغ أو لا يحتوي على بيانات.');
            }

            $headerRow = array_shift($rows);
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
        $productsByCode = Product::pluck('id', 'code');
        $provincesByName = Province::pluck('id', 'name');
        $suppliersCache = [];
        $pharmaciesCache = [];

        return collect($rows)->values()->map(function (array $row, int $index) use ($mapping, $productsByCode, $provincesByName, &$suppliersCache, &$pharmaciesCache) {
            $rowNumber = $index + 2;
            $raw = $this->extractRow($row, $mapping);
            $errors = [];

            $productCode = trim((string) ($raw['product_code'] ?? ''));
            $quantity = (int) ($raw['quantity'] ?? 0);
            $pharmacyName = trim((string) ($raw['pharmacy_name'] ?? ''));
            $soldAt = $this->parseDate($raw['sold_at'] ?? null);

            if ($productCode === '' || ! $productsByCode->has($productCode)) {
                $errors[] = [
                    'type' => self::ERROR_NOT_FOUND,
                    'column' => 'product_code',
                    'message' => "كود الصنف غير موجود: {$productCode}",
                ];
            }

            if ($quantity <= 0) {
                $errors[] = [
                    'type' => self::ERROR_VALIDATION,
                    'column' => 'quantity',
                    'message' => 'الكمية يجب أن تكون أكبر من صفر.',
                ];
            }

            if ($pharmacyName === '') {
                $errors[] = [
                    'type' => self::ERROR_VALIDATION,
                    'column' => 'pharmacy_name',
                    'message' => 'اسم الصيدلية مطلوب.',
                ];
            }

            if (! $soldAt) {
                $errors[] = [
                    'type' => self::ERROR_VALIDATION,
                    'column' => 'sold_at',
                    'message' => 'تاريخ البيع غير صالح.',
                ];
            }

            $provinceName = trim((string) ($raw['province_name'] ?? ''));
            $supplierName = trim((string) ($raw['supplier_name'] ?? ''));

            $provinceId = $provinceName !== '' ? $provincesByName->get($provinceName) : null;
            if ($provinceName !== '' && ! $provinceId) {
                $errors[] = [
                    'type' => self::ERROR_NOT_FOUND,
                    'column' => 'province_name',
                    'message' => "المحافظة غير موجودة: {$provinceName}",
                ];
            }

            $pharmacyId = null;
            $supplierId = null;

            if (empty($errors) && $provinceId) {
                $supplierKey = "{$provinceId}|{$supplierName}";
                if ($supplierName !== '') {
                    $supplierId = $suppliersCache[$supplierKey] ?? null;
                    if (! $supplierId) {
                        $supplier = Supplier::firstOrCreate(
                            ['province_id' => $provinceId, 'name' => $supplierName],
                            ['phone' => null, 'address' => null]
                        );
                        $supplierId = $supplier->id;
                        $suppliersCache[$supplierKey] = $supplierId;
                    }
                } else {
                    $supplier = Supplier::where('province_id', $provinceId)->first();
                    $supplierId = $supplier?->id;
                }

                if (! $supplierId) {
                    $errors[] = [
                        'type' => self::ERROR_NOT_FOUND,
                        'column' => 'supplier_name',
                        'message' => 'المورد مطلوب أو غير موجود في هذه المحافظة.',
                    ];
                } else {
                    $pharmacyKey = "{$supplierId}|{$pharmacyName}";
                    $pharmacyId = $pharmaciesCache[$pharmacyKey] ?? null;
                    if (! $pharmacyId) {
                        $pharmacy = Pharmacy::firstOrCreate(
                            ['supplier_id' => $supplierId, 'name' => $pharmacyName],
                            ['province_id' => $provinceId, 'phone' => null, 'address' => null]
                        );
                        $pharmacyId = $pharmacy->id;
                        $pharmaciesCache[$pharmacyKey] = $pharmacyId;
                    }
                }
            }

            $productId = $productsByCode->get($productCode);
            $importHash = null;

            if ($productId && isset($pharmacyId) && $soldAt) {
                $importHash = $this->buildImportHash($productId, $pharmacyId, $soldAt, $quantity);
            }

            return [
                'row_number' => $rowNumber,
                'raw' => $raw,
                'errors' => $errors,
                'product_id' => $productId,
                'pharmacy_id' => $pharmacyId ?? null,
                'supplier_id' => $supplierId ?? null,
                'province_id' => $provinceId,
                'quantity' => $quantity,
                'sold_at' => $soldAt,
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
        $hashes = $rows->pluck('import_hash')->filter();
        $duplicatesInFile = $hashes->duplicates()->values()->all();

        $existing = Sale::query()
            ->whereIn('import_hash', $hashes->unique()->values())
            ->pluck('import_hash')
            ->all();

        return array_values(array_unique(array_merge($duplicatesInFile, $existing)));
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
                'upload_batch_id' => $batch->id,
                'quantity' => $row['quantity'],
                'sold_at' => $row['sold_at'],
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
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildImportHash(int $productId, int $pharmacyId, string $soldAt, int $quantity): string
    {
        return hash('sha256', "{$productId}|{$pharmacyId}|{$soldAt}|{$quantity}");
    }
}
