<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index()
    {
        return view('order::index');
    }

    public function create()
    {
        return view('order::create');
    }

    public function store(Request $request) {}

    public function show($id)
    {
        return view('order::show');
    }

    public function edit($id)
    {
        return view('order::edit');
    }

    public function update(Request $request, $id) {}

    public function destroy($id) {}
}
