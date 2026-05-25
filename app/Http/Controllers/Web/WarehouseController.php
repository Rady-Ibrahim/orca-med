<?php

namespace App\Http\Controllers\Web;

use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\ProvinceService;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(
        private WarehouseService $service,
        private ProvinceService  $provinces,
    ) {}

    public function index(Request $request): View
    {
        $items     = $this->service->list($request->only(['search', 'per_page']));
        return view('warehouses.index', compact('items'));
    }

    public function create(): View
    {
        $provinces = $this->provinces->all();
        $types     = WarehouseType::cases();
        return view('warehouses.create', compact('provinces', 'types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:wholesale,retail'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'address'     => ['nullable', 'string', 'max:500'],
        ]);
        $warehouse = $this->service->create($data);
        $this->service->ensureShadowSupplier($warehouse);
        return redirect()->route('warehouses.index')->with('status', 'تم إنشاء المخزن بنجاح.');
    }

    public function edit(Warehouse $warehouse): View
    {
        $provinces = $this->provinces->all();
        $types     = WarehouseType::cases();
        return view('warehouses.edit', compact('warehouse', 'provinces', 'types'));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:wholesale,retail'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'address'     => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->update($warehouse, $data);
        return redirect()->route('warehouses.index')->with('status', 'تم تحديث بيانات المخزن بنجاح.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->service->delete($warehouse);
        return redirect()->route('warehouses.index')->with('status', 'تم حذف المخزن بنجاح.');
    }
}
