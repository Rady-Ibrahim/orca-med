<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $service) {}

    public function index(Request $request): View
    {
        $items = $this->service->list($request->only(['search', 'per_page']));
        return view('companies.index', compact('items'));
    }

    public function create(): View
    {
        return view('companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'is_active'     => ['boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->service->create($data);
        return redirect()->route('companies.index')->with('status', 'تمت إضافة الشركة بنجاح.');
    }

    public function edit(Company $company): View
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'is_active'     => ['boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $this->service->update($company, $data);
        return redirect()->route('companies.index')->with('status', 'تم تحديث بيانات الشركة بنجاح.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->service->delete($company);
        return redirect()->route('companies.index')->with('status', 'تم حذف الشركة بنجاح.');
    }
}
