<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;

class WorkingHourStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'day_of_week'   => ['required','in:' . implode(',', DayOfWeek::classConstants())],
            'hour'          => ['required','integer','min:0','max:23'],
            'type'          => ['required','in:add,remove'],
        ];
    }
}
