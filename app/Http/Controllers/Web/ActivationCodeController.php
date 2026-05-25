<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ActivationCodeController extends Controller
{
    public function index(): View
    {
        $codes = ActivationCode::with('company')
            ->latest()
            ->paginate(20);
        $companies = Company::all();
        return view('activation-codes.index', compact('codes', 'companies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'max_uses' => ['required', 'integer', 'min:1'],
        ]);

        $code = Str::upper(Str::random(8));

        ActivationCode::create([
            'code' => $code,
            'company_id' => $request->company_id,
            'duration_days' => $request->duration_days,
            'max_uses' => $request->max_uses,
            'used_count' => 0,
            'is_active' => true,
        ]);

        return back()->with('status', "تم إنشاء الكود: $code");
    }

    public function destroy(ActivationCode $code)
    {
        $code->delete();
        return back()->with('status', 'تم حذف الكود');
    }
}
