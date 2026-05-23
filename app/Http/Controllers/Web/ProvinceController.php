<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class ProvinceController extends Controller
{
    public function index() { return view('provinces.index'); }
    public function create() { return view('provinces.create'); }
    public function store() { return redirect()->route('provinces.index'); }
    public function edit($id) { return view('provinces.edit'); }
    public function update($id) { return redirect()->route('provinces.index'); }
    public function destroy($id) { return redirect()->route('provinces.index'); }
}
