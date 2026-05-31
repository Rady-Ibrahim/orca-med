<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Services\PharmacyService;
use App\Services\ProvinceService;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyController extends Controller
{
    public function __construct(
        private PharmacyService $service,
        private ProvinceService $provinces,
        private SupplierService $suppliers,
    ) {}

    public function index(Request $request): View
    {
        $items     = $this->service->list($request->only(['search', 'province_id', 'supplier_id', 'per_page']));
        $provinces = $this->provinces->all();
        $suppliers = $this->suppliers->list(['per_page' => 200])->getCollection();
        return view('pharmacies.index', compact('items', 'provinces', 'suppliers'));
    }

    public function create(): View
    {
        $provinces = $this->provinces->all();
        $suppliers = $this->suppliers->list(['per_page' => 200])->getCollection();
        return view('pharmacies.create', compact('provinces', 'suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'supplier_id'    => ['required', 'exists:suppliers,id'],
            'province_id'    => ['nullable', 'exists:provinces,id'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->create($data);
        return redirect()->route('pharmacies.index')->with('status', 'تمت إضافة الصيدلية بنجاح.');
    }

    public function edit(Pharmacy $pharmacy): View
    {
        $provinces = $this->provinces->all();
        $suppliers = $this->suppliers->list(['per_page' => 200])->getCollection();
        return view('pharmacies.edit', compact('pharmacy', 'provinces', 'suppliers'));
    }

    public function update(Request $request, Pharmacy $pharmacy): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'supplier_id'    => ['required', 'exists:suppliers,id'],
            'province_id'    => ['nullable', 'exists:provinces,id'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address'        => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->update($pharmacy, $data);
        return redirect()->route('pharmacies.index')->with('status', 'تم تحديث بيانات الصيدلية بنجاح.');
    }

    public function destroy(Pharmacy $pharmacy): RedirectResponse
    {
        $this->service->delete($pharmacy);
        return redirect()->route('pharmacies.index')->with('status', 'تم حذف الصيدلية بنجاح.');
    }

    public function show(Pharmacy $pharmacy): View
    {
        $pharmacy->load(['supplier', 'province', 'warehouse', 'sales.product']);

        // Aggregate sales by product to show total quantities
        $salesByProduct = \App\Models\Sale::where('pharmacy_id', $pharmacy->id)
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.code',
                \Illuminate\Support\Facades\DB::raw('SUM(sales.quantity) as total_quantity'),
                \Illuminate\Support\Facades\DB::raw('COUNT(sales.id) as transaction_count'),
                \Illuminate\Support\Facades\DB::raw('SUM(sales.quantity * sales.unit_price * (1 - sales.discount / 100)) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderByDesc('total_quantity')
            ->get();

        $user = auth()->user();

        // Privacy check: if company user without approved access, mask sensitive data
        $maskSensitiveData = false;
        if ($user->isCompanyUser()) {
            // Check if user has approved access for ANY product sold to this pharmacy
            $hasApprovedAccess = \App\Models\PharmacyAccessRequest::where('company_id', $user->company_id)
                ->where('status', \App\Enums\AccessRequestStatus::APPROVED)
                ->whereHas('product', function ($q) use ($pharmacy) {
                    $q->whereHas('sales', function ($sq) use ($pharmacy) {
                        $sq->where('pharmacy_id', $pharmacy->id);
                    });
                })
                ->exists();

            if (!$hasApprovedAccess) {
                $maskSensitiveData = true;
            }
        }

        return view('pharmacies.show', compact('pharmacy', 'maskSensitiveData', 'salesByProduct'));
    }
}
