<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSaleImportJob;
use App\Models\UploadBatch;
use App\Services\CompanyService;
use App\Services\ProvinceService;
use App\Services\SaleImportService;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        private SaleImportService $importService,
        private CompanyService $companyService,
        private SupplierService $supplierService,
        private ProvinceService $provinceService,
    ) {}

    public function index(): View
    {
        $batches = UploadBatch::with(['company', 'supplier', 'province', 'uploader'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $companies = $this->companyService->list(['per_page' => 200])->getCollection();
        $suppliers = $this->supplierService->list(['per_page' => 200])->getCollection();
        $provinces = $this->provinceService->list(['per_page' => 200])->getCollection();

        return view('imports.index', compact('batches', 'companies', 'suppliers', 'provinces'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'company_id' => ['required', 'exists:companies,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'province_id' => ['required', 'exists:provinces,id'],
        ]);

        $user = auth()->user();

        // Allow admins and company users to upload
        if (! $user->isAdmin() && ! $user->isCompanyUser()) {
            return redirect()->route('imports.index')
                ->with('error', 'غير مصرح لك برفع الملفات.');
        }

        // Company users can only upload for their own company
        if ($user->isCompanyUser() && $user->company_id !== $request->integer('company_id')) {
            return redirect()->route('imports.index')
                ->with('error', 'يمكنك الرفع لشركتك فقط.');
        }

        $batch = $this->importService->createQueuedBatch(
            $request->file('file'),
            $user,
            $request->integer('company_id'),
            $request->integer('supplier_id'),
            $request->integer('province_id')
        );

        // Detect similar products before processing
        try {
            $similarities = $this->importService->detectSimilarProductsInFile(
                $batch->stored_path,
                $batch->company_id
            );

            if (!empty($similarities)) {
                // Store similarities in session and redirect to reconciliation page
                session()->put('product_similarities', $similarities);
                session()->put('reconciliation_company_id', $batch->company_id);
                session()->put('reconciliation_upload_batch_id', $batch->id);

                return redirect()->route('products.reconciliation.index')
                    ->with('info', 'تم اكتشاف ' . count($similarities) . ' أسماء متشابهة. يرجى مراجعتها قبل المعالجة.');
            }
        } catch (\Throwable $e) {
            // If similarity detection fails, log error but continue with processing
            \Log::error('Similarity detection failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
        }

        // No similarities found or detection failed, proceed with processing
        ProcessSaleImportJob::dispatch($batch->id)->afterResponse();

        return redirect()->route('imports.index')->with('status', 'تم رفع الملف وبدء المعالجة.');
    }

    public function show(UploadBatch $batch): View
    {
        $batch->load(['company', 'supplier', 'province', 'uploader', 'errors']);

        return view('imports.show', compact('batch'));
    }

    public function template(): StreamedResponse
    {
        $headers = config('sale_import.columns');
        $columns = [];
        foreach ($headers as $canonical => $aliases) {
            $columns[] = $aliases[0] ?? $canonical;
        }

        $fileName = 'sales_import_template.csv';

        return response()->streamDownload(function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fclose($file);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function downloadErrors(UploadBatch $batch): StreamedResponse
    {
        if (! $batch->error_report_path) {
            abort(404, 'لا يوجد تقرير أخطاء لهذه الدفعة.');
        }

        $path = Storage::disk('local')->path($batch->error_report_path);

        if (! file_exists($path)) {
            abort(404, 'ملف التقرير غير موجود.');
        }

        return response()->download($path, "batch-{$batch->id}-errors.csv");
    }

    public function destroy(UploadBatch $batch)
    {
        $user = auth()->user();

        if (! $user->isAdmin()) {
            return redirect()->route('imports.index')
                ->with('error', 'فقط الأدمن يمكنه حذف الرفوعات.');
        }

        // Delete products created by this batch (only if not used in other batches' sales)
        $orphanedProductIds = \App\Models\Product::where('upload_batch_id', $batch->id)
            ->whereDoesntHave('sales', function ($query) use ($batch) {
                $query->where('upload_batch_id', '!=', $batch->id);
            })
            ->pluck('id');
        
        if ($orphanedProductIds->isNotEmpty()) {
            \App\Models\Product::whereIn('id', $orphanedProductIds)->delete();
        }

        // Delete pharmacies created by this batch (only if not used in other batches' sales)
        $orphanedPharmacyIds = \App\Models\Pharmacy::where('upload_batch_id', $batch->id)
            ->whereDoesntHave('sales', function ($query) use ($batch) {
                $query->where('upload_batch_id', '!=', $batch->id);
            })
            ->pluck('id');
        
        if ($orphanedPharmacyIds->isNotEmpty()) {
            \App\Models\Pharmacy::whereIn('id', $orphanedPharmacyIds)->delete();
        }

        // Delete the uploaded file from storage
        if ($batch->stored_path) {
            Storage::disk('local')->delete($batch->stored_path);
        }

        // Delete error report if exists
        if ($batch->error_report_path) {
            Storage::disk('local')->delete($batch->error_report_path);
        }

        // Delete the batch (cascade will delete sales and errors)
        $batch->delete();

        return redirect()->route('imports.index')->with('status', 'تم حذف الرفعة وكل البيانات المرتبطة بها بنجاح.');
    }
}
