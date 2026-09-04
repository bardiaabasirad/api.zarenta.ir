<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Http\Requests\IbanFromCardRequest;
use App\Http\Requests\IbanRequest;
use App\Http\Requests\MatchingRequest;
use App\Http\Requests\NcrRequest;
use App\Models\Setting;
use App\Services\JibitService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InquiryController extends Controller
{
    public function fee()
    {
        $settings = Setting::whereIn('option_key', [
            'cost_per_matching_inquiry',
            'cost_per_similarity_inquiry',
            'cost_per_shahkar_inquiry',
            'cost_per_iban_or_card_inquiry',
            'cost_per_iban_from_card_inquiry',
        ])->pluck('option_value', 'option_key')->toArray();

        return response()->json([
            'cost_per_matching_inquiry' => $settings['cost_per_matching_inquiry'] ?? null,
            'cost_per_similarity_inquiry' => $settings['cost_per_similarity_inquiry'] ?? null,
            'cost_per_shahkar_inquiry' => $settings['cost_per_shahkar_inquiry'] ?? null,
            'cost_per_iban_or_card_inquiry' => $settings['cost_per_iban_or_card_inquiry'] ?? null,
            'cost_per_iban_from_card_inquiry' => $settings['cost_per_iban_from_card_inquiry'] ?? null,
        ]);
    }

    public function matching(MatchingRequest $request)
    {
        // Access the authenticated API client
        $metalTrader = Auth::user();

        if (isset($request->card_number) || isset($request->iban)) {
            $costPerInquiry = Setting::where('option_key','cost_per_matching_inquiry')->first();
        }
        else {
            $costPerInquiry = Setting::where('option_key','cost_per_shahkar_inquiry')->first();
        }

        if ($metalTrader->balance < $costPerInquiry->option_value) {
            return response()->json([
                'code' => 'insufficient_funds',
                'balance' => $metalTrader->balance,
                'message' => 'موجودی حساب شما کافی نیست',
            ]);
        }

        try {
            $response = JibitService::matching($request);

            switch ($response['code']){
                case 'valid':
                    // Assuming $metalTrader and $inquiry are already defined
                    if (! $metalTrader->inquiries->contains($response['inquiry']->id)) {
                        // Sync the inquiry with the metalTrader
                        $metalTrader->decrement('balance', (int) $costPerInquiry->option_value);
                        $metalTrader->inquiries()->attach([$response['inquiry']->id]);
                    }

                    $message = '';
                    if (isset($request->iban)) $message = 'شماره شبا با کد ملی و تاریخ تولد تطابق دارد';
                    elseif (isset($request->card_number)) $message = 'شماره کارت با کد ملی و تاریخ تولد تطابق دارد';
                    elseif (isset($request->mobile_number)) $message = 'کد ملی و شماره همراه تطابق دارند';

                    return response()->json([
                        'code' => 'valid',
                        'balance' => $metalTrader->balance,
                        'message' => $message,
                    ]);
                case 'not_valid':
                    // Assuming $metalTrader and $inquiry are already defined
                    if (! $metalTrader->inquiries->contains($response['inquiry']->id)) {
                        // Sync the inquiry with the metalTrader
                        $metalTrader->decrement('balance', (int) $costPerInquiry->option_value);
                        $metalTrader->inquiries()->attach([$response['inquiry']->id]);
                    }

                    $message = '';
                    if (isset($request->iban)) $message = 'شماره شبا با کد ملی و تاریخ تولد تطابق ندارد';
                    elseif (isset($request->card_number)) $message = 'شماره کارت با کد ملی و تاریخ تولد تطابق ندارد';
                    elseif (isset($request->mobile_number)) $message = 'کد ملی و شماره همراه تطابق ندارند';

                    return response()->json([
                        'code' => 'not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => $message,
                    ]);
                case 'invalid.request_body':
                    return response()->json([
                        'code' => 'invalid_request_body',
                        'balance' => $metalTrader->balance,
                        'message' => 'بدنه درخواست خالی و یا نامعتبر است',
                    ]);
                case 'card.not_valid':
                    return response()->json([
                        'code' => 'card_not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => 'کارت نامعتبر است',
                    ]);
                case 'card.not_active':
                    return response()->json([
                        'code' => 'card_not_active',
                        'balance' => $metalTrader->balance,
                        'message' => 'کارت غیر فعال است',
                    ]);
                case 'card.is_expired':
                    return response()->json([
                        'code' => 'card_is_expired',
                        'balance' => $metalTrader->balance,
                        'message' => 'کارت منقضی شده است',
                    ]);
                case 'card.account_number_not_valid':
                    return response()->json([
                        'code' => 'card_account_number_not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => 'شماره حساب مربوط به کارت معتبر نیست',
                    ]);
                case 'card.owner_not_authorized':
                    return response()->json([
                        'code' => 'card_owner_not_authorized',
                        'balance' => $metalTrader->balance,
                        'message' => 'هویت دارنده کارت نامعتبر است',
                    ]);
                case 'card.registered_as_lost':
                    return response()->json([
                        'code' => 'card_registered_as_lost',
                        'balance' => $metalTrader->balance,
                        'message' => 'این کارت به عنوان کارت گم شده ثبت شده است',
                    ]);
                case 'card.registered_as_stolen':
                    return response()->json([
                        'code' => 'card_registered_as_stolen',
                        'balance' => $metalTrader->balance,
                        'message' => 'این کارت به عنوان کارت مسروقه ثبت شده است',
                    ]);
                case 'card.source_bank_is_not_active':
                    return response()->json([
                        'code' => 'card_source_bank_is_not_active',
                        'balance' => $metalTrader->balance,
                        'message' => 'بانک مربوط به کارت غیر فعال است',
                    ]);
                case 'card.provider_is_not_active':
                    return response()->json([
                        'code' => 'card_provider_is_not_active',
                        'balance' => $metalTrader->balance,
                        'message' => 'سرویس دهنده مربوط به کارت فعال نیست',
                    ]);
                case 'card.black_listed':
                    return response()->json([
                        'code' => 'card_black_listed',
                        'balance' => $metalTrader->balance,
                        'message' => 'استعلام این کارت امکان پذیر نیست',
                    ]);
                case 'identity_info.not_found':
                    return response()->json([
                        'code' => 'identity_info_not_found',
                        'balance' => $metalTrader->balance,
                        'message' => 'اطلاعات هویتی یافت نشد',
                    ]);
                case 'matching.unknown':
                    return response()->json([
                        'code' => 'matching_unknown',
                        'balance' => $metalTrader->balance,
                        'message' => 'تطابق نامشخص است',
                    ]);
                case 'iban.is_required':
                    return response()->json([
                        'code' => 'iban_is_required',
                        'balance' => $metalTrader->balance,
                        'message' => 'شماره شبا الزامی است',
                    ]);
                case 'iban.not_valid':
                    return response()->json([
                        'code' => 'iban_not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => 'شماره شبا نامعتبر است',
                    ]);
                case 'nationalCode.not_valid':
                    return response()->json([
                        'code' => 'national_code_not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => 'کد ملی نامعتبر است',
                    ]);
                case 'mobileNumber.not_valid':
                    return response()->json([
                        'code' => 'mobile_number_not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => 'شماره تلفن نامعتبر است',
                    ]);
                case 'daily_limit.reached':
                    return response()->json([
                        'code' => 'daily_limit_reached',
                        'balance' => $metalTrader->balance,
                        'message' => 'محدودیت روزانه سرویس استعلام',
                    ]);
                case 'forbidden':
                    Log::warning('Jibit token wrong, forbidden');

                    return response()->json([
                        'code' => 'forbidden',
                        'balance' => $metalTrader->balance,
                        'message' => 'عدم دسترسی به سرویس',
                    ]);
                case 'server.error':
                case 'providers.not_available':
                case 'handler.not_found':
                case 'curl_error':
                default:
                    return response()->json([
                        'code' => 'unknown_error',
                        'balance' => $metalTrader->balance,
                        'message' => 'خطای ناشناخته',
                    ]);
            }
        }
        catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code' => 'unknown_error',
                'balance' => $metalTrader->balance,
                'message' => 'خطای ناشناخته',
            ]);
        }
    }

    public function similarity(NcrRequest $request)
    {
        $metalTrader = Auth::user();

        $costPerInquiry = Setting::where('option_key','cost_per_similarity_inquiry')->first();

        if ($metalTrader->balance < $costPerInquiry->option_value) {
            return response()->json([
                'code' => 'insufficient_funds',
                'message' => 'موجودی حساب شما کافی نیست',
            ]);
        }

        $response = JibitService::similarity($request);

        switch ($response['code']) {
            case 'valid':
                $metalTrader->decrement('balance', (int) $costPerInquiry->option_value);
                $metalTrader->inquiries()->attach([$response['inquiry']->id]);

                return response()->json([
                    'code' => 'valid',
                    'balance' => $metalTrader->balance,
                    'first_name_similarity_percentage' => $response['firstNameSimilarityPercentage'],
                    'last_name_similarity_percentage' => $response['lastNameSimilarityPercentage'],
                    'full_name_similarity_percentage' => $response['fullNameSimilarityPercentage'],
                    'father_name_similarity_percentage' => $response['fatherNameSimilarityPercentage'],
                ]);
            case 'nationalCode.is_required':
                return response()->json([
                    'code' => 'national_code_is_required',
                    'balance' => $metalTrader->balance,
                    'message' => 'کد ملی ضروری است'
                ]);
            case 'nationalCode.not_valid':
                return response()->json([
                    'code' => 'national_code_not_valid',
                    'balance' => $metalTrader->balance,
                    'message' => 'کد ملی نامعتبر است'
                ]);
            case 'birthDate.is_required':
                return response()->json([
                    'code' => 'birthdate_is_required',
                    'balance' => $metalTrader->balance,
                    'message' => 'تاریخ تولد الزامی است'
                ]);
            case 'birthDate.not_valid':
                return response()->json([
                    'code' => 'birthdate_not_valid',
                    'balance' => $metalTrader->balance,
                    'message' => 'تاریخ تولد نامعتبر است'
                ]);
            case 'query_parameters.not_provided':
                return response()->json([
                    'code' => 'query_parameters_not_provided',
                    'balance' => $metalTrader->balance,
                    'message' => 'پارامترهای درخواست کافی نیست'
                ]);
            case 'identity_info.found_not':
                return response()->json([
                    'code' => 'identity_info_found_not',
                    'balance' => $metalTrader->balance,
                    'message' => 'اطلاعات هویتی یافت نشد'
                ]);
            case 'daily_limit.reached':
                return response()->json([
                    'code' => 'daily_limit_reached',
                    'balance' => $metalTrader->balance,
                    'message' => 'محدودیت روزانه سرویس استعلام'
                ]);
            case 'invalid.request_body':
                return response()->json([
                    'code' => 'invalid_request_body',
                    'balance' => $metalTrader->balance,
                    'message' => 'بدنه درخواست نامعتبر است'
                ]);
            case 'forbidden':
                Log::warning('Jibit token wrong, forbidden');

                return response()->json([
                    'code' => 'forbidden',
                    'balance' => $metalTrader->balance,
                    'message' => 'عدم دسترسی به سرویس'
                ]);
            case 'providers.not_available':
            case 'curl_error':
            case 'server.error':
            default:
                return response()->json([
                    'code' => 'unknown_error',
                    'balance' => $metalTrader->balance,
                    'message' => 'خطای ناشناخته'
                ]);
        }
    }

    public function iban(IbanRequest $request)
    {
        $metalTrader = Auth::user();

        $costPerInquiry = Setting::where('option_key','cost_per_iban_or_card_inquiry')->first();

        if ($metalTrader->balance < $costPerInquiry->option_value) {
            return response()->json([
                'code' => 'insufficient_funds',
                'balance' => $metalTrader->balance,
                'message' => 'موجودی حساب شما کافی نیست',
            ]);
        }

        if (strlen($request->iban) == 16){
            $response = JibitService::cards($request);

            switch ($response['code']) {
                case 'valid':
                    $metalTrader->decrement('balance', (int) $costPerInquiry->option_value);
                    $metalTrader->inquiries()->attach([$response['inquiry']->id]);

                    return response()->json([
                        'code' => 'valid',
                        'balance' => $metalTrader->balance,
                        'info' => $response['inquiry'],
                    ]);
                case 'card.account_number_not_valid':
                    return response()->json([
                        'code' => 'card_account_number_not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => 'شماره حساب مربوط به کارت معتبر نیست'
                    ]);
                case 'card.owner_not_authorized':
                    return response()->json([
                        'code' => 'card_owner_not_authorized',
                        'balance' => $metalTrader->balance,
                        'message' => 'هویت دارنده کارت در سیستم بانکی نامعتبر است'
                    ]);
                case 'card.registered_as_lost':
                    return response()->json([
                        'code' => 'card_registered_as_lost',
                        'balance' => $metalTrader->balance,
                        'message' => 'این کارت به عنوان کارت گمشده ثبت شده است'
                    ]);
                case 'card.registered_as_stolen':
                    return response()->json([
                        'code' => 'card_registered_as_stolen',
                        'balance' => $metalTrader->balance,
                        'message' => 'این کارت به عنوان کارت مسروقه ثبت شده است'
                    ]);
                case 'card.source_bank_is_not_active':
                    return response()->json([
                        'code' => 'card_source_bank_is_not_active',
                        'balance' => $metalTrader->balance,
                        'message' => 'بانک مربوط به کارت فعال نیست'
                    ]);
                case 'card.black_listed':
                    return response()->json([
                        'code' => 'card_black_listed',
                        'balance' => $metalTrader->balance,
                        'message' => 'استعلام این کارت امکان پذیر نیست'
                    ]);
                case 'card.provider_is_not_active':
                    return response()->json([
                        'code' => 'card_provider_is_not_active',
                        'balance' => $metalTrader->balance,
                        'message' => 'سرویس دهنده مربوط به کارت غیر فعال است'
                    ]);
                case 'card.is_required':
                    return response()->json([
                        'code' => 'card_is_required',
                        'balance' => $metalTrader->balance,
                        'message' => 'کارت الزامی است'
                    ]);
                case 'card.not_valid':
                    return response()->json([
                        'code' => 'card_not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => 'کارت نامعتبر است'
                    ]);
                case 'card.not_active':
                    return response()->json([
                        'code' => 'card_not_active',
                        'balance' => $metalTrader->balance,
                        'message' => 'کارت غیر فعال است'
                    ]);
                case 'card.is_expired':
                    return response()->json([
                        'code' => 'card_is_expired',
                        'balance' => $metalTrader->balance,
                        'message' => 'کارت منقضی شده است'
                    ]);
                case 'parameters.not_acceptable':
                    return response()->json([
                        'code' => 'parameters_not_acceptable',
                        'balance' => $metalTrader->balance,
                        'message' => 'تنها یکی از موارد شبا، حساب و کد ملی باید true تنظیم شوند'
                    ]);
                case 'invalid.request_body':
                    return response()->json([
                        'code' => 'invalid_request_body',
                        'balance' => $metalTrader->balance,
                        'message' => 'بدنه درخواست نامعتبر است'
                    ]);
                case 'forbidden':
                    Log::warning('Jibit token wrong, forbidden');

                    return response()->json([
                        'code' => 'forbidden',
                        'balance' => $metalTrader->balance,
                        'message' => 'عدم دسترسی به سرویس'
                    ]);
                case 'daily_limit.reached':
                    return response()->json([
                        'code' => 'daily_limit_reached',
                        'balance' => $metalTrader->balance,
                        'message' => 'محدودیت روزانه سرویس استعلام'
                    ]);
                case 'providers.not_available':
                case 'curl_error':
                case 'server.error':
                default:
                    return response()->json([
                        'code' => 'unknown_error',
                        'balance' => $metalTrader->balance,
                        'message' => 'خطای ناشناخته'
                    ]);
            }
        }
        else{
            $response = JibitService::iban($request);

            switch ($response['code']) {
                case 'valid':
                    $metalTrader->decrement('balance', (int) $costPerInquiry->option_value);
                    $metalTrader->inquiries()->attach([$response['inquiry']->id]);

                    return response()->json([
                        'code' => 'valid',
                        'balance' => $metalTrader->balance,
                        'info' => $response['inquiry'],
                    ]);
                case 'iban.not_found':
                    return response()->json([
                        'code' => 'iban_not_found',
                        'balance' => $metalTrader->balance,
                        'message' => 'شبا یافت نشد'
                    ]);
                case 'iban.owner_not_found':
                    return response()->json([
                        'code' => 'iban_owner_not_found',
                        'balance' => $metalTrader->balance,
                        'message' => 'صاحب شبا یافت نشد'
                    ]);
                case 'invalid.request_body':
                    return response()->json([
                        'code' => 'invalid_request_body',
                        'balance' => $metalTrader->balance,
                        'message' => 'بدنه درخواست نامعتبر است'
                    ]);
                case 'forbidden':
                    Log::warning('Jibit token wrong, forbidden');

                    return response()->json([
                        'code' => 'forbidden',
                        'balance' => $metalTrader->balance,
                        'message' => 'عدم دسترسی به سرویس'
                    ]);
                case 'iban.is_required':
                    return response()->json([
                        'code' => 'iban_is_required',
                        'balance' => $metalTrader->balance,
                        'message' => 'شبا الزامی است'
                    ]);
                case 'iban.not_valid':
                    return response()->json([
                        'code' => 'iban_not_valid',
                        'balance' => $metalTrader->balance,
                        'message' => 'شبا نامعتبر است'
                    ]);
                case 'daily_limit.reached':
                    return response()->json([
                        'code' => 'daily_limit_reached',
                        'balance' => $metalTrader->balance,
                        'message' => 'محدودیت روزانه سرویس استعلام'
                    ]);
                case 'providers.not_available':
                case 'curl_error':
                case 'server.error':
                default:
                    return response()->json([
                        'code' => 'unknown_error',
                        'balance' => $metalTrader->balance,
                        'message' => 'خطای ناشناخته'
                    ]);
            }
        }
    }
    public function ibanFromCard(IbanFromCardRequest $request)
    {
        $metalTrader = Auth::user();

        $costPerInquiry = Setting::where('option_key','cost_per_iban_or_card_inquiry')->first();

        if ($metalTrader->balance < $costPerInquiry->option_value) {
            return response()->json([
                'code' => 'insufficient_funds',
                'balance' => $metalTrader->balance,
                'message' => 'موجودی حساب شما کافی نیست',
            ]);
        }

        $response = JibitService::ibanFromCard($request);

        switch ($response['code']) {
            case 'valid':
                $inquiryId = $response['inquiry']->id;
                $metalTrader->decrement('balance', (int) $costPerInquiry->option_value);
                $metalTrader->inquiries()->attach([$inquiryId]);

//                if (!$metalTrader->inquiries()->where('inquiry_id', $inquiryId)->exists()) {
//                    $metalTrader->decrement('balance', (int) $costPerInquiry->option_value);
//                    $metalTrader->inquiries()->attach([$inquiryId]);
//                }

                return response()->json([
                    'code' => 'valid',
                    'balance' => $metalTrader->balance,
                    'info' => $response['inquiry'],
                ]);
            case 'card.registered_as_lost':
                return response()->json([
                    'code' => 'card_registered_as_lost',
                    'balance' => $metalTrader->balance,
                    'message' => 'این کارت به عنوان کارت گمشده ثبت شده است'
                ]);
            case 'card.registered_as_stolen':
                return response()->json([
                    'code' => 'card_registered_as_stolen',
                    'balance' => $metalTrader->balance,
                    'message' => 'این کارت به عنوان کارت مسروقه ثبت شده است'
                ]);
            case 'card.source_bank_is_not_active':
                return response()->json([
                    'code' => 'card_source_bank_is_not_active',
                    'balance' => $metalTrader->balance,
                    'message' => 'بانک مربوط به کارت فعال نیست'
                ]);
            case 'card.provider_is_not_active':
                return response()->json([
                    'code' => 'card_provider_is_not_active',
                    'balance' => $metalTrader->balance,
                    'message' => 'سرویس دهنده مربوط به کارت غیر فعال است'
                ]);
            case 'iban.not_found':
                return response()->json([
                    'code' => 'iban_not_found',
                    'balance' => $metalTrader->balance,
                    'message' => 'شبا یافت نشد'
                ]);
            case 'iban.not_valid':
                return response()->json([
                    'code' => 'iban_not_valid',
                    'balance' => $metalTrader->balance,
                    'message' => 'شبا نامعتبر است'
                ]);
            case 'iban.owner_not_found':
                return response()->json([
                    'code' => 'iban_owner_not_found',
                    'balance' => $metalTrader->balance,
                    'message' => 'دارنده شبا یافت نشد'
                ]);
            case 'card.black_listed':
                return response()->json([
                    'code' => 'card_black_listed',
                    'balance' => $metalTrader->balance,
                    'message' => 'استعلام این کارت امکان پذیر نیست'
                ]);
            case 'conversion.failed':
                return response()->json([
                    'code' => 'conversion_failed',
                    'balance' => $metalTrader->balance,
                    'message' => 'تبدیل امکان پذیر نیست'
                ]);
            case 'card.is_required':
                return response()->json([
                    'code' => 'card_is_required',
                    'balance' => $metalTrader->balance,
                    'message' => 'کارت الزامی است'
                ]);
            case 'card.not_valid':
                return response()->json([
                    'code' => 'card_not_valid',
                    'balance' => $metalTrader->balance,
                    'message' => 'کارت نامعتبر است'
                ]);
            case 'card_type.not_supported':
                return response()->json([
                    'code' => 'card_type_not_supported',
                    'balance' => $metalTrader->balance,
                    'message' => 'نوع کارت ورودی پشتیبانی نمی‌شود'
                ]);
            case 'parameters.not_acceptable':
                return response()->json([
                    'code' => 'parameters_not_acceptable',
                    'balance' => $metalTrader->balance,
                    'message' => 'تنها یکی از موارد شبا، حساب و کد ملی باید true تنظیم شوند'
                ]);
            case 'card.not_active':
                return response()->json([
                    'code' => 'card_not_active',
                    'balance' => $metalTrader->balance,
                    'message' => 'کارت غیر فعال است'
                ]);
            case 'card.is_expired':
                return response()->json([
                    'code' => 'card_is_expired',
                    'balance' => $metalTrader->balance,
                    'message' => 'کارت منقضی شده است'
                ]);
            case 'card.account_number_not_valid':
                return response()->json([
                    'code' => 'card_account_number_not_valid',
                    'balance' => $metalTrader->balance,
                    'message' => 'شماره حساب مربوط به کارت معتبر نیست'
                ]);
            case 'card.owner_not_authorized':
                return response()->json([
                    'code' => 'card_owner_not_authorized',
                    'balance' => $metalTrader->balance,
                    'message' => 'هویت دارنده کارت در سیستم بانکی نامعتبر است'
                ]);
            case 'invalid.request_body':
                return response()->json([
                    'code' => 'invalid_request_body',
                    'balance' => $metalTrader->balance,
                    'message' => 'بدنه درخواست نامعتبر است'
                ]);
            case 'forbidden':
                Log::warning('Jibit token wrong, forbidden');

                return response()->json([
                    'code' => 'forbidden',
                    'balance' => $metalTrader->balance,
                    'message' => 'عدم دسترسی به سرویس'
                ]);
            case 'daily_limit.reached':
                return response()->json([
                    'code' => 'daily_limit_reached',
                    'balance' => $metalTrader->balance,
                    'message' => 'محدودیت روزانه سرویس استعلام'
                ]);
            case 'providers.not_available':
            case 'curl_error':
            case 'server.error':
            default:
                return response()->json([
                    'code' => 'unknown_error',
                    'balance' => $metalTrader->balance,
                    'message' => 'خطای ناشناخته'
                ]);
        }
    }
}
