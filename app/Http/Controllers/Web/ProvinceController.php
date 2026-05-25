<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Services\ProvinceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProvinceController extends Controller
{
    public function __construct(private ProvinceService $service) {}

    public function index(Request $request): View
    {
        $items = $this->service->list($request->only(['search', 'per_page']));
        return view('provinces.index', compact('items'));
    }

    public function create(): View
    {
        return view('provinces.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:provinces,name'],
        ]);
        $this->service->create($data);
        return redirect()->route('provinces.index')->with('status', 'تمت إضافة المحافظة بنجاح.');
    }

    public function edit(Province $province): View
    {
        return view('provinces.edit', compact('province'));
    }

    public function update(Request $request, Province $province): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:provinces,name,'.$province->id],
        ]);
        $this->service->update($province, $data);
        return redirect()->route('provinces.index')->with('status', 'تم تحديث المحافظة بنجاح.');
    }

    public function destroy(Province $province): RedirectResponse
    {
        $this->service->delete($province);
        return redirect()->route('provinces.index')->with('status', 'تم حذف المحافظة بنجاح.');
    }
}
