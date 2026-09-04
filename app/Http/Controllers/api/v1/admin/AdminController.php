<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminRequest;
use App\Http\Requests\UpdateAdminRequest;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::query();

        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');

        $admins = $admins->with('roles')
            ->orderBy($sortBy??'created_at', $dir??'asc')
            ->paginate($count??config('app.per_page'));

        return response()->json($admins);
    }

    public function permissions(Request $request): JsonResponse
    {
        $permissions = $request->user()
            ->allPermissions()
            ->map(fn ($permission) => [
                'id'   => $permission->id,
                'name' => $permission->name,
            ])
            ->values();

        return response()->json($permissions);
    }

    public function show(Admin $staff)
    {
        $staff->load('roles', 'permissions');
        $roles = Role::all();

        return response()->json([
            'staff' => $staff,
            'roles' => $roles,
        ]);
    }

    public function store(StoreAdminRequest $request)
    {
        $attributes = Arr::except($request->validated(), 'password');

        if ($request->filled('password')) {
            $attributes['password'] = bcrypt($request->input('password'));
        }

        $staff = Admin::create($attributes);

        if($request->roles){
            $staff->roles()->attach($request->roles);
        }

        return \response()->json(['message' => trans('messages.a_new_operator_has_been_created'),], 201);
    }

    public function update(UpdateAdminRequest $request, Admin $staff)
    {
        $attributes = Arr::except($request->validated(), ['password','image']);

        if ($request->filled('password')) {
            $attributes['password'] = bcrypt($request->input('password'));
        }

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = Storage::put('operators', $image);
            $attributes['avatar'] = $path;
        }

        if($request->roles){
            $staff->roles()->sync($request->roles);
        }

        $staff->update($attributes);

        return response()->json([
            'staff' => $staff->load('roles'),
            'message' => trans('messages.the_operator_has_been_successfully_updated'),
        ]);
    }

    public function info()
    {
        return response()->json(
            Auth::guard('admin-api')->user()->only(['id', 'full_name', 'avatar', 'phone'])
        );
    }
}
