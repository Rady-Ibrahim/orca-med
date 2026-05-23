<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class SearchController extends Controller
{
    public function index() { return view('search.index'); }
}
