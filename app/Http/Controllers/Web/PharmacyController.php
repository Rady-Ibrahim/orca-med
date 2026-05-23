<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class PharmacyController extends Controller
{
    public function index() { return view('pharmacies.index'); }
    public function create() { return view('pharmacies.create'); }
    public function store() { return redirect()->route('pharmacies.index'); }
    public function edit($id) { return view('pharmacies.edit'); }
    public function update($id) { return redirect()->route('pharmacies.index'); }
    public function destroy($id) { return redirect()->route('pharmacies.index'); }
}
