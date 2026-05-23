<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function index(): View
    {
        $user  = auth()->user();
        $stats = $this->dashboard->getStats($user);

        $view = match ($user->role?->value) {
            'company'   => 'dashboard.company',
            'warehouse' => 'dashboard.warehouse',
            default     => 'dashboard.admin',
        };

        return view($view, [
            'totals' => $stats['totals'],
            'charts' => $stats['charts'],
        ]);
    }
}
