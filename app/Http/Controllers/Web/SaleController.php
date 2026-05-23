<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class SaleController extends Controller
{
    public function index() { return view('sales.index'); }
    public function create() { return view('sales.create'); }
    public function store() { return redirect()->route('sales.index'); }
}
