<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\UploadBatch;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        $query = Sale::with(['pharmacy', 'product', 'supplier', 'province', 'uploadBatch']);

        // Filter by company for company users
        if (auth()->user()->isCompanyUser() && auth()->user()->company_id) {
            $batchIds = UploadBatch::where('company_id', auth()->user()->company_id)
                ->pluck('id');
            $query->whereIn('upload_batch_id', $batchIds);
        }

        $sales = $query->latest('sold_at')->paginate(50);
        return view('sales.index', compact('sales'));
    }

    public function create() { return view('sales.create'); }
    public function store() { return redirect()->route('sales.index'); }
}
