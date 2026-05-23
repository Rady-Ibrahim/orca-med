<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SensitiveUnlockController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isCompanyUser() || ! $user->company_id) {
            return response()->json(['message' => 'هذه الخاصية لمستخدمي الشركات فقط.'], 403);
        }

        $company = Company::findOrFail($user->company_id);

        if (! $company->sensitive_view_password) {
            return response()->json([
                'message' => 'لم يتم ضبط كلمة المرور الإضافية من المشرف بعد.',
            ], 422);
        }

        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], $company->sensitive_view_password)) {
            throw ValidationException::withMessages([
                'password' => ['كلمة المرور غير صحيحة.'],
            ]);
        }

        $ttl = (int) config('orca.sensitive_unlock_ttl_minutes', 120);
        $user->update([
            'sensitive_unlock_expires_at' => now()->addMinutes($ttl),
        ]);

        return response()->json([
            'message' => 'تم فتح عرض البيانات التفصيلية مؤقتاً.',
            'expires_at' => $user->fresh()->sensitive_unlock_expires_at?->toIso8601String(),
        ]);
    }
}
