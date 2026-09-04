<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Morilog\Jalali\CalendarUtils;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class MatchingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'card_number' => 'required_without_all:iban,mobile_number',
            'iban' => 'required_without_all:card_number,mobile_number',
            'mobile_number' => 'required_without_all:card_number,iban',
            'national_code' => 'required',
            'birthdate' => 'required_with:card_number,iban',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'code' => 'invalid_request_body',
                'message' => 'بدنه درخواست خالی و یا نامعتبر است',
            ], Response::HTTP_UNPROCESSABLE_ENTITY) // 422 status code
        );
    }
}
