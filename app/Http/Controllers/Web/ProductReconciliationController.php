<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductSimilarityService;
use Illuminate\Http\Request;
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
        $similarities = Session::get('product_similarities', []);
        $companyId = Session::get('reconciliation_company_id');
        $uploadBatchId = Session::get('reconciliation_upload_batch_id');

        if (empty($similarities)) {
            return redirect()
                ->route('imports.index')
                ->with('error', 'لا توجد أسماء متشابهة للتصحيح.');
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
        $companyId = $request->input('company_id');
        $uploadBatchId = $request->input('upload_batch_id');

        // Store the reconciliation choices in session
        Session::put('reconciliation_choices', $choices);
        Session::put('reconciliation_company_id', $companyId);
        Session::put('reconciliation_upload_batch_id', $uploadBatchId);

        // Clear the similarities from session
        Session::forget('product_similarities');

        // Process the batch with reconciliation choices
        $batch = \App\Models\UploadBatch::find($uploadBatchId);
        if ($batch) {
            \App\Jobs\ProcessSaleImportJob::dispatch($batch->id)->afterResponse();
        }

        return redirect()
            ->route('imports.index')
            ->with('success', 'تم حفظ اختيارات التصحيح. جاري معالجة الملف...');
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
            'results' => $similar->map(fn ($item) => [
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
