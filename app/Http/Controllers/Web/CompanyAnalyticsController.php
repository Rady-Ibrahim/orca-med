<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class CompanyAnalyticsController extends Controller
{
    public function products() { return view('company-analytics.products'); }
    public function provinces($product) { return view('company-analytics.provinces', compact('product')); }
    public function pharmacies($product) { return view('company-analytics.pharmacies', compact('product')); }
    public function sensitiveUnlock() { return redirect()->back(); }
}
