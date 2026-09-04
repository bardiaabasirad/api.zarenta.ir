require("dotenv").config();
const axios = require("axios");

/**
 * 🚀 سرویس ارسال نرخ‌ها به سرور
 * - جلوگیری از ارسال تکراری همزمان
 * - پشتیبانی از inFlightRequests خارجی (برای هماهنگی با card-scraper)
 */
class RateService {
    constructor(apiBaseUrl, priceSourceId) {
        this.apiBaseUrl = apiBaseUrl;
        this.priceSourceId = priceSourceId;

        // fallback داخلی در صورت عدم ارسال Map از بیرون
        this.inFlightRequests = new Map();
    }

    /**
     * انتخاب Map مناسب برای مدیریت درخواست‌های درحال اجرا
     */
    _getInFlightMap(externalMap) {
        if (
            externalMap &&
            typeof externalMap.has === "function" &&
            typeof externalMap.set === "function"
        ) {
            return externalMap;
        }
        return this.inFlightRequests;
    }

    /**
     * تولید hash امن و پایدار برای یک درخواست
     */
    _buildRequestHash(formattedData, generateHash) {
        if (typeof generateHash === "function") {
            return generateHash(formattedData, this.priceSourceId);
        }

        // fallback ساده در صورت نبود تابع تولید هش
        return JSON.stringify({
            price_source_id: this.priceSourceId,
            items: formattedData,
        });
    }

    /**
     * ارسال داده‌ها به سرور با جلوگیری از تداخل درخواست‌های مشابه
     *
     * @param {Object} params
     * @param {Array} params.formattedData - داده‌های استخراج شده
     * @param {number} params.cardIndex - ایندکس کارت جهت لاگ
     * @param {string} params.trigger - منشا درخواست (observer یا initial)
     * @param {Function} params.generateHash - تابع تولید هش
     * @param {Map} [params.inFlightRequests] - Map بیرونی برای dedupe مشترک
     * @returns {Promise<boolean>}
     */
    async sendRateToServer({
                               formattedData,
                               cardIndex,
                               trigger,
                               generateHash,
                               inFlightRequests,
                           }) {
        const requestList = Array.isArray(formattedData) ? formattedData : [];
        if (requestList.length === 0) {
            return false;
        }

        const inFlightMap = this._getInFlightMap(inFlightRequests);
        const requestHash = this._buildRequestHash(requestList, generateHash);

        // جلوگیری از ارسال همزمان درخواست‌های مشابه که هنوز پاسخی دریافت نکرده‌اند
        if (inFlightMap.has(requestHash)) {
            console.log(
                `🚫 [${trigger}] Duplicate in-flight request discarded ` +
                `[card ${cardIndex}]`
            );
            return false;
        }

        inFlightMap.set(requestHash, {
            timestamp: Date.now(),
            trigger,
            cardIndex,
            count: requestList.length,
        });

        const url = `${this.apiBaseUrl}/api/new-rate`;

        try {
            console.log(
                `📤 [${trigger}] Sending ${requestList.length} item(s) to: ${url}`
            );

            const response = await axios.post(
                url,
                {
                    items: requestList,
                    price_source_id: this.priceSourceId,
                },
                {
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-Api-Secret": process.env.INTERNAL_API_SECRET,
                    },
                    timeout: 10000,
                    validateStatus: (status) => status >= 200 && status < 300,
                }
            );

            if (!response) {
                throw new Error("Empty response received from API");
            }

            console.log(
                `✅ [${trigger}] card[${cardIndex}] - Sent successfully ` +
                `(${requestList.length} item(s))`
            );

            return true;
        } catch (err) {
            const message = err?.message || "Unknown error";
            const isNetworkNoise =
                /ECONNREFUSED|timeout|ETIMEDOUT|ENOTFOUND|ECONNRESET/i.test(
                    message
                );

            if (!isNetworkNoise) {
                console.error(
                    `⚠️ [RateService] Axios error [card ${cardIndex}]: ${message}`
                );
            } else {
                console.warn(
                    `⚠️ [RateService] Network issue [card ${cardIndex}]: ${message}`
                );
            }

            // خطا مجدداً پرتاب می‌شود تا فرآیندهای بالا دستی (مانند retry) مطلع شوند
            throw err;
        } finally {
            inFlightMap.delete(requestHash);
        }
    }
}

module.exports = RateService;
