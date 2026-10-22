<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;

class WorkingHourStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'changes'                 => ['required', 'array', 'min:1'],
            'changes.*.day_of_week'   => ['required', 'in:' . implode(',', DayOfWeek::classConstants())],
            'changes.*.hour'          => ['required', 'integer', 'min:0', 'max:23'],
            'changes.*.action'        => ['required', 'in:add,remove'],
        ];
    }
}
