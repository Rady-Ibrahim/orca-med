<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class ImportController extends Controller
{
    public function index() { return view('imports.index'); }
    public function store() { return redirect()->route('imports.index'); }
    public function show($batch) { return view('imports.show', ['batch' => $batch]); }
    public function template() { abort(501, 'قريباً'); }
}
