<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkingHourStoreRequest;
use App\Models\WorkingHour;
use Illuminate\Support\Facades\DB;

class WorkingHourController extends Controller
{
    public function index()
    {
        $workingHours = WorkingHour::all();

        return response()->json([
            'data' => $workingHours,
        ]);
    }

    public function store(WorkingHourStoreRequest $request)
    {
        $validatedData = $request->validated();

        DB::transaction(function () use ($validatedData) {
            foreach ($validatedData['changes'] as $change) {
                $this->applyHour(
                    $change['day_of_week'],
                    (int) $change['hour'],
                    $change['action']
                );
            }
        });

        $workingHours = WorkingHour::all();

        return response()->json([
            'data' => $workingHours,
        ]);
    }

    private function applyHour(string $dayOfWeek, int $hour, string $action): void
    {
        $day = WorkingHour::where('day_of_week', $dayOfWeek)->first();

        if (!$day) {
            return;
        }

        $workingHours = $day->working_hours ?? [];
        $workingHours = array_map('intval', $workingHours);

        if ($action === 'add') {
            if (!in_array($hour, $workingHours, true)) {
                $workingHours[] = $hour;
                sort($workingHours);
            }
        } else {
            $workingHours = array_values(array_filter($workingHours, function ($existingHour) use ($hour) {
                return (int) $existingHour !== $hour;
            }));
        }

        $day->working_hours = $workingHours;
        $day->save();
    }
}
