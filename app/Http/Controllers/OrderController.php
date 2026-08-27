<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $allowed = ['pending', 'processing', 'shipping', 'completed', 'cancelled'];
        $orders = $request->user()->orders()->with('items')->when(in_array($status, $allowed, true), fn ($query) => $query->where('status', $status))->latest()->get();

        return view('orders.index', compact('orders', 'status'));
    }
}
