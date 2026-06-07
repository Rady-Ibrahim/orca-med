<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Jobs\ProcessSaleImportJob;
use App\Models\UploadBatch;
use App\Services\ProductSimilarityService;
use App\Services\UploadBatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ProductReconciliationController extends Controller
{
    public function __construct(
        private ProductSimilarityService $similarityService
    ) {}

    /**
     * Show the reconciliation page with similar products
     */
    public function index(Request $request)
    {
        $batchId = (int) $request->query('batch');

        // If batch parameter is provided, always load from batch (ignore session)
        if ($batchId > 0) {
            $batch = UploadBatch::with(['errors' => function ($query) {
                $query->where('error_type', UploadBatchService::ERROR_AMBIGUOUS_PRODUCT)
                    ->orderBy('row_number');
            }])->find($batchId);

            if (! $batch) {
                return redirect()
                    ->route('imports.index')
                    ->with('error', 'الرفعة غير موجودة.');
            }

            $user = $request->user();

            if (! $user?->isAdmin() && ! ($user?->isCompanyUser() && $user->company_id === $batch->company_id)) {
                return redirect()
                    ->route('imports.index')
                    ->with('error', 'غير مصرح لك بالوصول لهذه الرفعة.');
            }

            $errors = $batch->errors;
            $similarities = [];

            if ($errors->isEmpty()) {
                $sessionSimilarities = Session::get('product_similarities', []);
                $sessionBatchId = Session::get('reconciliation_upload_batch_id');

                if ($sessionBatchId === $batch->id && ! empty($sessionSimilarities)) {
                    $similarities = collect($sessionSimilarities)
                        ->map(function ($item) {
                            if (! isset($item['row_number'])) {
                                if (! empty($item['rows']) && is_array($item['rows'])) {
                                    $item['row_number'] = $item['rows'][0];
                                } else {
                                    $item['row_number'] = null;
                                }
                            }

                            if (! isset($item['count'])) {
                                $item['count'] = ! empty($item['rows']) && is_array($item['rows'])
                                    ? count($item['rows'])
                                    : 1;
                            }

                            return $item;
                        })
                        ->values()
                        ->all();
                } else {
                    return redirect()
                        ->route('imports.show', $batch->id)
                        ->with('error', 'لا توجد أخطاء تصحيح منتجات لهذه الرفعة.');
                }
            }

            if (empty($similarities)) {
                $grouped = [];

                foreach ($errors as $error) {
                    $rawProductName = $error->row_data['raw']['product_name'] ?? null;

                    if (! $rawProductName) {
                        continue;
                    }

                    if (! isset($grouped[$rawProductName])) {
                        $grouped[$rawProductName] = [
                            'original' => $rawProductName,
                            'rows' => [],
                            'count' => 0,
                            'candidate_data' => [],
                        ];
                    }

                    $grouped[$rawProductName]['count']++;
                    $grouped[$rawProductName]['rows'][] = $error->row_number;

                    foreach ($error->row_data['candidates'] ?? [] as $candidate) {
                        $productId = $candidate['product_id'] ?? null;
                        $similarity = $candidate['similarity'] ?? 0;

                        if (! $productId) {
                            continue;
                        }

                        if (
                            ! isset($grouped[$rawProductName]['candidate_data'][$productId])
                            || $similarity > $grouped[$rawProductName]['candidate_data'][$productId]['similarity']
                        ) {
                            $grouped[$rawProductName]['candidate_data'][$productId] = [
                                'similarity' => $similarity,
                            ];
                        }
                    }
                }

                $candidateIds = collect();
                foreach ($grouped as $item) {
                    $candidateIds = $candidateIds->merge(array_keys($item['candidate_data']));
                }

                $candidateProducts = Product::whereIn('id', $candidateIds->unique()->all())
                    ->get()
                    ->keyBy('id');

                $groupedResults = [];
                foreach ($grouped as $item) {
                    $similar = [];
                    foreach ($item['candidate_data'] as $productId => $candidateInfo) {
                        if ($candidateProducts->has($productId)) {
                            $similar[] = [
                                'product' => $candidateProducts->get($productId),
                                'similarity' => $candidateInfo['similarity'] / 100,
                            ];
                        }
                    }

                    $groupedResults[] = [
                        'original' => $item['original'],
                        'rows' => array_unique($item['rows']),
                        'count' => $item['count'],
                        'row_number' => reset($item['rows']),
                        'similar' => $similar,
                    ];
                }

                $similarities = $groupedResults;
            }

            $companyId = $batch->company_id;
            $uploadBatchId = $batch->id;

            // Update session
            Session::put('product_similarities', $similarities);
            Session::put('reconciliation_company_id', $companyId);
            Session::put('reconciliation_upload_batch_id', $uploadBatchId);
        } else {
            // Fallback to session if no batch parameter
            $similarities = collect(Session::get('product_similarities', []))
                ->map(function ($item) {
                    if (! isset($item['row_number'])) {
                        if (! empty($item['rows']) && is_array($item['rows'])) {
                            $item['row_number'] = $item['rows'][0];
                        } else {
                            $item['row_number'] = null;
                        }
                    }

                    return $item;
                })
                ->values()
                ->all();

            $companyId = Session::get('reconciliation_company_id');
            $uploadBatchId = Session::get('reconciliation_upload_batch_id');
        }

        if (empty($similarities)) {
            return redirect()
                ->route('imports.index')
                ->with('error', 'لا توجد أسماء متشابهة للتصحيح.');
        }

        if (! $companyId || ! $uploadBatchId) {
            return redirect()
                ->route('imports.index')
                ->with('error', 'لم يتم العثور على معلومات الرفعة. يرجى المحاولة مرة أخرى.');
        }

        return view('products.reconciliation', [
            'similarities' => $similarities,
            'company_id' => $companyId,
            'upload_batch_id' => $uploadBatchId,
        ]);
    }

    /**
     * Save user's reconciliation choices
     */
    public function store(Request $request)
    {
        $request->validate([
            'choices' => 'required|array',
            'choices.*.original' => 'required|string',
            'choices.*.selected_product_id' => 'nullable|integer',
            'choices.*.create_new' => 'nullable|boolean',
        ]);

        $choices = $request->input('choices', []);
        $companyId = $request->input('company_id') ?: Session::get('reconciliation_company_id');
        $uploadBatchId = $request->input('upload_batch_id') ?: Session::get('reconciliation_upload_batch_id');

        if (! $companyId || ! $uploadBatchId) {
            return redirect()
                ->route('imports.index')
                ->with('error', 'لم يتم العثور على معلومات الرفعة. يرجى المحاولة مرة أخرى.');
        }

        $batch = \App\Models\UploadBatch::find($uploadBatchId);
        if (! $batch) {
            return redirect()
                ->route('imports.index')
                ->with('error', 'الرفعة غير موجودة.');
        }

        // Use batch's company_id instead of relying on form input
        $companyId = $batch->company_id;

        $uploadBatchService = app(\App\Services\UploadBatchService::class);
        $user = $request->user();

        $ambiguousErrors = $batch->errors()
            ->where('error_type', UploadBatchService::ERROR_AMBIGUOUS_PRODUCT)
            ->get()
            ->groupBy(fn ($error) => $error->row_data['raw']['product_name'] ?? '');

        $hadDbErrors = $ambiguousErrors->isNotEmpty();

        if ($hadDbErrors) {
            foreach ($choices as $choice) {
                $originalName = $choice['original'];
                $createNew = ! empty($choice['create_new']);
                $selectedProductId = $choice['selected_product_id'] ?? null;

                $errors = $ambiguousErrors->get($originalName, collect());

                foreach ($errors as $error) {
                    if ($createNew) {
                        $data = [
                            'row_number' => $error->row_number,
                            'action' => 'create_new',
                        ];
                    } elseif ($selectedProductId) {
                        $data = [
                            'row_number' => $error->row_number,
                            'action' => 'map_to_existing',
                            'product_id' => $selectedProductId,
                        ];
                    } else {
                        continue;
                    }

                    try {
                        $uploadBatchService->resolveAmbiguousProduct($batch, $data, $user);
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        Log::error('Failed to resolve ambiguous product', [
                            'row_number' => $error->row_number,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } else {
            $this->savePreImportAliases($choices, $companyId);
        }

        $remainingErrors = $batch->errors()
            ->where('error_type', UploadBatchService::ERROR_AMBIGUOUS_PRODUCT)
            ->exists();

        if ($hadDbErrors) {
            $message = $remainingErrors
                ? 'تم حفظ اختيارات التصحيح. ما زالت هناك صفوف تحتاج تصحيح.'
                : 'تم حفظ اختيارات التصحيح بنجاح.';
            $redirectRoute = $remainingErrors
                ? route('products.reconciliation.index', ['batch' => $batch->id])
                : route('imports.index');
        } else {
            ProcessSaleImportJob::dispatch($batch->id)->afterResponse();
            $message = 'تم حفظ اختيارات التصحيح وبدء معالجة الملف.';
            $redirectRoute = route('imports.index');
        }

        // Clear reconciliation data from session
        Session::forget('product_similarities');
        Session::forget('reconciliation_choices');
        Session::forget('reconciliation_company_id');
        Session::forget('reconciliation_upload_batch_id');

        return redirect($redirectRoute)
            ->with('success', $message);
    }

    /**
     * Search for products to help user select the correct one
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'company_id' => 'required|integer',
        ]);

        $query = $request->input('query');
        $companyId = $request->input('company_id');

        // Find similar products
        $similar = $this->similarityService->findSimilarProducts(
            $query,
            $companyId,
            0.6, // Lower threshold for search
            10
        );

        return response()->json([
            'results' => $similar->map(fn($item) => [
                'id' => $item['product']->id,
                'name' => $item['product']->name,
                'code' => $item['product']->code,
                'similarity' => round($item['similarity'] * 100, 1),
            ])->toArray(),
        ]);
    }

    /**
     * Get details of a specific product
     */
    public function showProduct(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
        ]);

        $product = Product::findOrFail($request->input('product_id'));

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'description' => $product->description,
            'company' => $product->company?->name,
        ]);
    }

    /**
     * Process the import file after reconciliation
     */
    private function savePreImportAliases(array $choices, int $companyId): void
    {
        foreach ($choices as $choice) {
            if (! empty($choice['create_new'])) {
                continue;
            }

            $originalName = trim((string) ($choice['original'] ?? ''));
            $productId = $choice['selected_product_id'] ?? null;

            if ($originalName === '' || ! $productId) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product || $product->company_id !== $companyId) {
                continue;
            }

            ProductAlias::updateOrCreate(
                ['alias_name' => $originalName],
                ['product_id' => $product->id]
            );
        }
    }

    public function processAfterReconciliation(Request $request)
    {
        $choices = session('reconciliation_choices', []);
        $uploadBatchId = session('reconciliation_upload_batch_id');

        if (!$uploadBatchId) {
            return redirect()
                ->route('imports.index')
                ->with('error', 'لم يتم العثور على معلومات الرفعة.');
        }

        $batch = \App\Models\UploadBatch::find($uploadBatchId);
        if (!$batch) {
            return redirect()
                ->route('imports.index')
                ->with('error', 'الرفعة غير موجودة.');
        }

        // Dispatch the import job
        \App\Jobs\ProcessSaleImportJob::dispatch($batch->id)->afterResponse();

        // Clear reconciliation data from session
        session()->forget('reconciliation_choices');
        session()->forget('reconciliation_company_id');
        session()->forget('reconciliation_upload_batch_id');

        return redirect()
            ->route('imports.index')
            ->with('success', 'تم حفظ الاختيارات وبدء معالجة الملف.');
    }
}
