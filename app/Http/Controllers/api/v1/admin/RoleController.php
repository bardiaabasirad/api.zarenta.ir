<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;


class RoleController extends Controller
{
    public function all()
    {
        $roles = Role::all();

        return \response()->json($roles);
    }

    public function index()
    {
        $roles = Role::query();

        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $display_name = request()->input('display_name');
        $description = request()->input('description');

        $roles = $roles
            ->when($display_name, function($query) use ($display_name) {
                $query->where('display_name', 'like' , "%{$display_name}%");
            })
            ->when($description, function($query) use ($description) {
                $query->where('description', 'like' , "%{$description}%");
            })
            ->orderBy($sortBy??'created_at', $dir??'asc')
            ->paginate($count??config('app.per_page'));

        return \response()->json($roles);
    }

    public function store(StoreRoleRequest $request)
    {
        $name = $this->roleSlug($request->display_name);

        $role = Role::create([
            'name' => $name,
            'display_name' => $request->display_name,
            'description' => $request->description,
        ]);

        $role->permissions()->attach($request->selected_permissions);

        return response()->json(['message'=> trans('messages.a_new_access_level_has_been_created')]);
    }

    public function update(Role $role, UpdateRoleRequest $request)
    {
        if($request->display_name) {
            $role->name = $this->roleSlug($request->display_name);
            $role->display_name = $request->display_name;
            $role->save();
        }
        if($request->description) {
            $role->description = $request->description;
            $role->save();
        }
        if($request->selected_permissions) {
            $role->permissions()->sync($request->selected_permissions);
        }

        return response()->json([
            'role' => $role->load('permissions'),
            'message'=> trans('messages.the_access_level_has_been_successfully_updated')
        ]);
    }

    public function show(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::all();

        return response()->json([
            'role' => $role,
            'permissions' => $permissions
        ]);
    }

    /**
     * @param $display_name
     * @return string
     */
    private function roleSlug(string $display_name): string
    {
        $name = createPersianSlug($display_name);
        $counter = 1;

        while (Role::where('name', $name)->exists()) {
            $name .= $counter;
            $counter++;
        }
        return $name;
    }
}
