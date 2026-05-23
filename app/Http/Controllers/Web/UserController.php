<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class UserController extends Controller
{
    public function index() { return view('users.index'); }
    public function create() { return view('users.create'); }
    public function store() { return redirect()->route('users.index'); }
    public function edit($id) { return view('users.edit'); }
    public function update($id) { return redirect()->route('users.index'); }
    public function destroy($id) { return redirect()->route('users.index'); }
}
