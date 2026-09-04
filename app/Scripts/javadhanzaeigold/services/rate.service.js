require("dotenv").config();
const axios = require('axios');

/**
 * 🚀 سرویس ارسال نرخ‌ها به سرور
 */
class RateService {
    constructor(apiBaseUrl, priceSourceId) {
        this.apiBaseUrl = apiBaseUrl;
        this.priceSourceId = priceSourceId;
        this.inFlightRequests = new Map();
    }

    /**
     * 📤 ارسال داده‌ها به سرور با مکانیزم جلوگیری از ارسال تکراری همزمان
     * @param {Object} params
     * @param {Object} params.formattedData - داده‌های استخراج شده
     * @param {number} params.cardIndex - ایندکس کارت جهت لاگ
     * @param {string} params.trigger - منشا درخواست (مثلاً Interval یا DOM)
     * @param {Function} params.generateHash - تابع تولید هش (Dependency Injection)
     * @returns {Promise<boolean>}
     */
    async sendRateToServer({ formattedData, cardIndex, trigger, generateHash }) {
        const requestHash = generateHash(formattedData, this.priceSourceId);

        // ✅ جلوگیری از تداخل درخواست‌های مشابه که هنوز تمام نشده‌اند
        if (this.inFlightRequests.has(requestHash)) {
            console.log(`🚫 [${trigger}] Duplicate in-flight request - Discarded [card ${cardIndex}]`);
            return false;
        }

        // ثبت در لیست در حال پردازش
        this.inFlightRequests.set(requestHash, {
            timestamp: Date.now(),
            trigger,
            cardIndex
        });

        try {
            console.log(`📤 [${trigger}] Sending ${Object.keys(formattedData).length} item(s) to: ${this.apiBaseUrl}/api/new-rate`);

            await axios.post(`${this.apiBaseUrl}/api/new-rate`, {
                items: formattedData,
                price_source_id: this.priceSourceId
            }, {
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-Api-Secret": process.env.INTERNAL_API_SECRET
                },
                timeout: 10000,
            });

            console.log(`✅ [${trigger}] card[${cardIndex}] - Sent successfully`);
            return true;

        } catch (err) {
            if (!/ECONNREFUSED|timeout|ENOTFOUND/.test(err.message)) {
                console.error(`⚠️ [RateService] Axios error [card ${cardIndex}]: ${err.message}`);
            }
            throw err;
        } finally {
            // آزاد کردن هش برای درخواست‌های بعدی
            this.inFlightRequests.delete(requestHash);
        }
    }
}

module.exports = RateService;
