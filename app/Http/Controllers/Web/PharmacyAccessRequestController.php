<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class PharmacyAccessRequestController extends Controller
{
    public function index() { return view('access-requests.index'); }
    public function store() { return redirect()->back()->with('status', 'تم إرسال الطلب.'); }
    public function update($id) { return redirect()->route('access-requests.index'); }
}
