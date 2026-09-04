<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PuppeteerController extends Controller
{
    public function store(Request $request)
    {
        $response = Http::post('http://localhost:3000/order', [
            'action' => $request->action,
            'delivery' => $request->delivery,
            'amount' => $request->amount
        ]);

        return $response->json();
    }
}
