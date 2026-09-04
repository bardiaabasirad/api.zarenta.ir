<?php

namespace App\Services;

use App\Models\MetalTraderLead;

class MetalTraderLeadService
{
    /**
     * ثبت شماره تلفن به عنوان لید (در صورت عدم وجود، ایجاد و در صورت وجود، زمان تلاش بروزرسانی می‌شود)
     *
     * @param string $phone
     * @param string $lead_type
     * @return void
     */
    public function track(string $phone, string $lead_type = 'signup_abandoned'): void
    {
        // با استفاده از updateOrCreate، از ثبت شماره‌های تکراری جلوگیری کرده و آخرین زمان تلاش را بروزرسانی می‌کنیم
        MetalTraderLead::updateOrCreate(
            ['phone' => $phone],
            [
                'last_attempt_at' => now(),
                'lead_type' => $lead_type,
            ]
        );
    }

    /**
     * حذف شماره از لیست لیدها پس از اتمام موفقیت‌آمیز ثبت‌نام
     *
     * @param string $phone
     * @return void
     */
    public function remove(string $phone): void
    {
        MetalTraderLead::where('phone', $phone)->delete();
    }
}
