<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Http\Requests\StoreAddressRequest;
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
                    $query->with('province','shippingMethod');
                }
            ])
            ->get();
        $physical_addresses = PhysicalAddress::with('city.province')->get();

        return response()->json([
            'addresses' => $addresses,
            'physical_addresses' => $physical_addresses,
        ]);
    }

    public function create()
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

    public function get(Address $address)
    {
        Gate::authorize('view', $address);

        $address->load([
                'city' => function($query){
                    $query->with('province','shippingMethod');
                }
            ])
            ->get();

        return response()->json([
           'address' => $address,
        ]);
    }

    public function store(AddressRequest $request)
    {
        $address = Address::create($request->validated()+[
            'user_id' => Auth::guard('user-api')->id()
        ]);

        return response()->json([
           'new_address' => $address->load('city.province')
        ]);
    }

    public function update(AddressRequest $request, Address $address)
    {
        Gate::authorize('update', $address);

        $address->update($request->validated());

        return response()->json([
           'address' => $address->load('city.province'),
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
