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
        $this->syncHour($validatedData['day_of_week'], $validatedData['hour']);

        $workingHours = WorkingHour::all();

        return response()->json([
            'data' => $workingHours,
        ]);
    }

    private function syncHour($dayOfWeek, $workingHour)
    {
        $day = WorkingHour::where('day_of_week', $dayOfWeek)->first();

        if ($day) {
            $workingHours = $day->working_hours;

            if (!in_array($workingHour, $workingHours)) {
                // Add $workingHour to the array and sort it
                $workingHours[] = (int)$workingHour;
                sort($workingHours);
            }
            else {
                // Remove $workingHour from the array and reindex with numerical keys
                $workingHours = array_values(array_filter($workingHours, function ($hour) use ($workingHour) {
                    return (int)$hour !== (int)$workingHour;
                }));
            }

            // Update the day in the database
            DB::table('working_hours')->where('day_of_week', $dayOfWeek)->update(['working_hours' => $workingHours]);
        }
    }
}
