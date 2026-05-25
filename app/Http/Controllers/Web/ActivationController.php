<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function index(): View
    {
        return view('activation.index');
    }

    public function activate(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = ActivationCode::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (! $code || !$code->isAvailable()) {
            return back()->with('error', 'كود التفعيل غير صالح أو منتهي الصلاحية.');
        }

        $user = auth()->user();

        if (! $user->isCompanyUser() || ! $user->company_id) {
            return back()->with('error', 'فقط مستخدمي الشركات يمكنهم تفعيل الكود.');
        }

        if ($code->company_id && $code->company_id !== $user->company_id) {
            return back()->with('error', 'هذا الكود مخصص لشركة أخرى.');
        }

        // Increment used count
        $code->increment('used_count');

        // Activate analytics for user
        $user->update([
            'analytics_unlock_expires_at' => now()->addDays($code->duration_days),
        ]);

        return redirect()->route('dashboard')->with('status', "تم تفعيل التحليلات بنجاح! (صالح لمدة {$code->duration_days} يوم)");
    }
}
