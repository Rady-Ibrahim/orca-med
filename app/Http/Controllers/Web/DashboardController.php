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

        $data = [
            'totals' => $stats['totals'],
            'charts' => $stats['charts'],
        ];

        // Add quantity summaries for company users
        if ($user->isCompanyUser()) {
            $data['quantity_summaries'] = $this->dashboard->getQuantitySummaries($user);
            
            // Add pharmacy details for activated companies
            if ($user->hasAnalyticsAccess()) {
                $data['pharmacy_details'] = $this->dashboard->getPharmacyDetails($user);
            }
        }

        return view($view, $data);
    }
}
