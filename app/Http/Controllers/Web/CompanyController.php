<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class CompanyController extends Controller
{
    public function index() { return view('companies.index'); }
    public function create() { return view('companies.create'); }
    public function store() { return redirect()->route('companies.index'); }
    public function edit($id) { return view('companies.edit'); }
    public function update($id) { return redirect()->route('companies.index'); }
    public function destroy($id) { return redirect()->route('companies.index'); }
}
