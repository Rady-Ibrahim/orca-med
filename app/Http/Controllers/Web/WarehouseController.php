<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class WarehouseController extends Controller
{
    public function index() { return view('warehouses.index'); }
    public function create() { return view('warehouses.create'); }
    public function store() { return redirect()->route('warehouses.index'); }
    public function edit($id) { return view('warehouses.edit'); }
    public function update($id) { return redirect()->route('warehouses.index'); }
    public function destroy($id) { return redirect()->route('warehouses.index'); }
}
