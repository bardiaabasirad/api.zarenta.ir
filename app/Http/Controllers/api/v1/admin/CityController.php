<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Broker;
use App\Models\City;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::query();

        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');

        $cities = $cities->orderBy($sortBy??'created_at', $dir??'asc')->get();


        return response()->json($cities);
    }
}
