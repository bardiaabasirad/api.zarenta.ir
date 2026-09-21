<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Models\Address;
use App\Models\City;
use App\Models\PhysicalAddress;
use App\Models\Province;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function getUserAddresses()
    {
        $addresses = Address::where('user_id', Auth::guard('user-api')->id())
            ->with([
                'city' => function($query){
                    $query->with('province','shippingMethods');
                }
            ])
            ->get();
        $physical_addresses = PhysicalAddress::with('city.province')->get();

        return response()->json([
            'addresses' => $addresses,
            'physical_addresses' => $physical_addresses,
        ]);
    }

    public function getProvincesAndCities()
    {
        return response()->json([
           'provinces' => Province::all(),
           'cities' => City::all(),
        ]);
    }

    public function show(Address $address)
    {
        Gate::authorize('view', $address);

        $address->load('city.province');

        return response()->json([
           'address' => $address,
           'provinces' => Province::all(),
           'cities' => City::all(),
        ]);
    }

    public function store(AddressRequest $request)
    {
        $address = Address::create($request->validated() + [
            'user_id' => Auth::guard('user-api')->id()
        ]);

        $address->load([
            'city' => function($query) {
                $query->with(['province', 'shippingMethods']);
            }
        ]);

        return response()->json([
            'new_address' => $address
        ]);
    }


    public function update(AddressRequest $request, Address $address)
    {
        Gate::authorize('update', $address);

        $address->update($request->validated());

        $address->load([
            'city' => function($query) {
                $query->with(['province', 'shippingMethods']);
            }
        ]);

        return response()->json([
           'address' => $address,
           'message' => trans('messages.address_updated_successfully'),
        ]);
    }

    public function destroy(Address $address)
    {
        Gate::authorize('delete', $address);

        $address->delete();

        return response()->json([
            'message' => trans('messages.address_removed_successfully')
        ]);
    }
}
