<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;


class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::get();

        return \response()->json($permissions);
    }
}
