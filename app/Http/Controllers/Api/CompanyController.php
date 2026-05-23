<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return CompanyResource::collection(
            $this->companyService->list($request->only(['search', 'per_page']))
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:companies,name'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        return (new CompanyResource($this->companyService->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Company $company): JsonResponse
    {
        $company->loadCount(['products', 'users']);

        return (new CompanyResource($company))->response();
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:companies,name,'.$company->id],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        return (new CompanyResource($this->companyService->update($company, $data)))->response();
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->companyService->delete($company);

        return response()->json(['message' => 'تم الحذف.']);
    }

    /**
     * Set or clear the extra password company users must enter to temporarily view sensitive pharmacy-level data.
     */
    public function updateSensitiveViewPassword(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'sensitive_view_password' => ['present', 'nullable', 'string'],
        ]);

        $raw = $data['sensitive_view_password'];
        if ($raw !== null && $raw !== '' && strlen($raw) < 6) {
            throw ValidationException::withMessages([
                'sensitive_view_password' => ['يجب أن تكون كلمة المرور 6 أحرف على الأقل أو اتركها فارغة للإلغاء.'],
            ]);
        }

        $company->update([
            'sensitive_view_password' => ($raw === null || $raw === '') ? null : $raw,
        ]);

        return (new CompanyResource($company->fresh()))->response();
    }
}
