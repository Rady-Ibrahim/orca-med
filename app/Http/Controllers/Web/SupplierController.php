<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\ProvinceService;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierService $service,
        private ProvinceService $provinces,
    ) {}

    public function index(Request $request): View
    {
        $items    = $this->service->list($request->only(['search', 'province_id', 'per_page']));
        $provinces = $this->provinces->all();
        return view('suppliers.index', compact('items', 'provinces'));
    }

    public function create(): View
    {
        $provinces = $this->provinces->all();
        return view('suppliers.create', compact('provinces'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'province_id' => ['required', 'exists:provinces,id'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'address'     => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->create($data);
        return redirect()->route('suppliers.index')->with('status', 'تمت إضافة المورد بنجاح.');
    }

    public function edit(Supplier $supplier): View
    {
        $provinces = $this->provinces->all();
        return view('suppliers.edit', compact('supplier', 'provinces'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'province_id' => ['required', 'exists:provinces,id'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'address'     => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->update($supplier, $data);
        return redirect()->route('suppliers.index')->with('status', 'تم تحديث بيانات المورد بنجاح.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->service->delete($supplier);
        return redirect()->route('suppliers.index')->with('status', 'تم حذف المورد بنجاح.');
    }
}
