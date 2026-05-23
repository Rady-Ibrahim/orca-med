<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class SupplierController extends Controller
{
    public function index() { return view('suppliers.index'); }
    public function create() { return view('suppliers.create'); }
    public function store() { return redirect()->route('suppliers.index'); }
    public function edit($id) { return view('suppliers.edit'); }
    public function update($id) { return redirect()->route('suppliers.index'); }
    public function destroy($id) { return redirect()->route('suppliers.index'); }
}
