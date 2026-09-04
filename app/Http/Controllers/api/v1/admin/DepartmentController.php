<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentStoreRequest;
use App\Models\Department;

class DepartmentController extends Controller
{
    public function index()
    {
        $data = Department::query();
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $departmentName = request()->input('department_name');

        $data = $data
            ->when(isset($departmentName), function ($query) use ($departmentName) {
                $query->where('name', $departmentName);
            })
            ->orderBy($sortBy ?? 'created_at', $dir ?? 'desc')
            ->paginate($count ?? config('app.per_page'));

        return response()->json([
            'data' => $data
        ]);
    }

    public function show(Department $department)
    {
        return response()->json([
            'data' => $department,
        ]);
    }

    public function store(DepartmentStoreRequest $request)
    {
        $validated = $request->validated();

        $department = Department::create($validated);

        return response()->json([
            'message' => 'دپارتمان با موفقیت ایجاد شد.',
            'data' => $department
        ], 201);
    }

    public function update(DepartmentStoreRequest $request, Department $department)
    {
        $validated = $request->validated();

        $department->update($validated);

        return response()->json([
            'message' => 'دپارتمان با موفقیت بروزرسانی شد.',
            'data' => $department
        ]);
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return response()->json([
            'message' => 'دپارتمان با موفقیت حذف شد.',
        ]);
    }
}
