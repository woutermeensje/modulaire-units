<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\QuoteCart;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(QuoteCart $cart): View
    {
        return view('portal.dashboard', [
            'quoteCount' => $cart->count(),
        ]);
    }
}
