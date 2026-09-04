<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchingRequest;
use App\Http\Requests\NcrRequest;
use App\Services\JibitService;
use Illuminate\Support\Facades\DB;

class InquiryController extends Controller
{
    public function matching(MatchingRequest $request)
    {
        try {
            $response = JibitService::matching($request);

            switch ($response['code']){
                case 'valid':
                    $message = '';
                    if (isset($request->iban)) $message = 'شماره شبا با کد ملی و تاریخ تولد تطابق دارد';
                    elseif (isset($request->card_number)) $message = 'شماره کارت با کد ملی و تاریخ تولد تطابق دارد';
                    elseif (isset($request->mobile_number)) $message = 'کد ملی و شماره همراه تطابق دارند';

                    return response()->json([
                        'code' => 'valid',
                        'message' => $message,
                    ]);
                case 'not_valid':
                    $message = '';
                    if (isset($request->iban)) $message = 'شماره شبا با کد ملی و تاریخ تولد تطابق ندارد';
                    elseif (isset($request->card_number)) $message = 'شماره کارت با کد ملی و تاریخ تولد تطابق ندارد';
                    elseif (isset($request->mobile_number)) $message = 'کد ملی و شماره همراه تطابق ندارند';

                    return response()->json([
                        'code' => 'not_valid',
                        'message' => $message,
                    ]);
                case 'invalid.request_body':
                    return response()->json([
                        'code' => 'invalid_request_body',
                        'message' => 'بدنه درخواست خالی و یا نامعتبر است',
                    ]);
                case 'card.not_valid':
                    return response()->json([
                        'code' => 'card_not_valid',
                        'message' => 'کارت نامعتبر است',
                    ]);
                case 'card.not_active':
                    return response()->json([
                        'code' => 'card_not_active',
                        'message' => 'کارت غیر فعال است',
                    ]);
                case 'card.is_expired':
                    return response()->json([
                        'code' => 'card_is_expired',
                        'message' => 'کارت منقضی شده است',
                    ]);
                case 'card.account_number_not_valid':
                    return response()->json([
                        'code' => 'card_account_number_not_valid',
                        'message' => 'شماره حساب مربوط به کارت معتبر نیست',
                    ]);
                case 'card.owner_not_authorized':
                    return response()->json([
                        'code' => 'card_owner_not_authorized',
                        'message' => 'هویت دارنده کارت نامعتبر است',
                    ]);
                case 'card.registered_as_lost':
                    return response()->json([
                        'code' => 'card_registered_as_lost',
                        'message' => 'این کارت به عنوان کارت گم شده ثبت شده است',
                    ]);
                case 'card.registered_as_stolen':
                    return response()->json([
                        'code' => 'card_registered_as_stolen',
                        'message' => 'این کارت به عنوان کارت مسروقه ثبت شده است',
                    ]);
                case 'card.source_bank_is_not_active':
                    return response()->json([
                        'code' => 'card_source_bank_is_not_active',
                        'message' => 'بانک مربوط به کارت غیر فعال است',
                    ]);
                case 'card.provider_is_not_active':
                    return response()->json([
                        'code' => 'card_provider_is_not_active',
                        'message' => 'سرویس دهنده مربوط به کارت فعال نیست',
                    ]);
                case 'card.black_listed':
                    return response()->json([
                        'code' => 'card_black_listed',
                        'message' => 'استعلام این کارت امکان پذیر نیست',
                    ]);
                case 'identity_info.not_found':
                    return response()->json([
                        'code' => 'identity_info_not_found',
                        'message' => 'اطلاعات هویتی یافت نشد',
                    ]);
                case 'matching.unknown':
                    return response()->json([
                        'code' => 'matching_unknown',
                        'message' => 'تطابق نامشخص است',
                    ]);
                case 'iban.is_required':
                    return response()->json([
                        'code' => 'iban_is_required',
                        'message' => 'شماره شبا الزامی است',
                    ]);
                case 'iban.not_valid':
                    return response()->json([
                        'code' => 'iban_not_valid',
                        'message' => 'شماره شبا نامعتبر است',
                    ]);
                case 'nationalCode.not_valid':
                    return response()->json([
                        'code' => 'national_code_not_valid',
                        'message' => 'کد ملی نامعتبر است',
                    ]);
                case 'mobileNumber.not_valid':
                    return response()->json([
                        'code' => 'mobile_number_not_valid',
                        'message' => 'شماره تلفن نامعتبر است',
                    ]);
                case 'daily_limit.reached':
                    return response()->json([
                        'code' => 'daily_limit_reached',
                        'message' => 'محدودیت روزانه سرویس استعلام',
                    ]);
                case 'forbidden':
                    return response()->json([
                        'code' => 'forbidden',
                        'message' => 'عدم دسترسی به سرویس',
                    ]);
                case 'server.error':
                case 'providers.not_available':
                case 'handler.not_found':
                case 'curl_error':
                default:
                    return response()->json([
                        'code' => 'unknown_error',
                        'message' => 'خطای ناشناخته',
                    ]);
            }
        }
        catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code' => 'unknown_error',
                'message' => 'خطای ناشناخته',
            ]);
        }
    }

    public function similarity(NcrRequest $request)
    {
        $response = JibitService::similarity($request);

        switch ($response['code']) {
            case 'valid':
                return response()->json([
                    'code' => 'valid',
                    'first_name_similarity_percentage' => $response['firstNameSimilarityPercentage'],
                    'last_name_similarity_percentage' => $response['lastNameSimilarityPercentage'],
                    'full_name_similarity_percentage' => $response['fullNameSimilarityPercentage'],
                    'father_name_similarity_percentage' => $response['fatherNameSimilarityPercentage'],
                ]);
            case 'nationalCode.is_required':
                return response()->json([
                    'code' => 'national_code_is_required',
                    'message' => 'کد ملی ضروری است'
                ]);
            case 'nationalCode.not_valid':
                return response()->json([
                    'code' => 'national_code_not_valid',
                    'message' => 'کد ملی نامعتبر است'
                ]);
            case 'birthDate.is_required':
                return response()->json([
                    'code' => 'birthdate_is_required',
                    'message' => 'تاریخ تولد الزامی است'
                ]);
            case 'birthDate.not_valid':
                return response()->json([
                    'code' => 'birthdate_not_valid',
                    'message' => 'تاریخ تولد نامعتبر است'
                ]);
            case 'query_parameters.not_provided':
                return response()->json([
                    'code' => 'query_parameters_not_provided',
                    'message' => 'پارامترهای درخواست کافی نیست'
                ]);
            case 'identity_info.found_not':
                return response()->json([
                    'code' => 'identity_info_found_not',
                    'message' => 'اطلاعات هویتی یافت نشد'
                ]);
            case 'daily_limit.reached':
                return response()->json([
                    'code' => 'daily_limit_reached',
                    'message' => 'محدودیت روزانه سرویس استعلام'
                ]);
            case 'invalid.request_body':
                return response()->json([
                    'code' => 'invalid_request_body',
                    'message' => 'بدنه درخواست نامعتبر است'
                ]);
            case 'forbidden':
                return response()->json([
                    'code' => 'forbidden',
                    'message' => 'عدم دسترسی به سرویس'
                ]);
            case 'providers.not_available':
            case 'curl_error':
            case 'server.error':
            default:
                return response()->json([
                    'code' => 'unknown_error',
                    'message' => 'خطای ناشناخته'
                ]);
        }
    }
}
