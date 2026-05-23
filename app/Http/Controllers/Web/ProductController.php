<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function index() { return view('products.index'); }
    public function create() { return view('products.create'); }
    public function store() { return redirect()->route('products.index'); }
    public function edit($id) { return view('products.edit'); }
    public function update($id) { return redirect()->route('products.index'); }
    public function destroy($id) { return redirect()->route('products.index'); }
}
