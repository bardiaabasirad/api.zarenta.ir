require("dotenv").config();
const axios = require('axios');

/**
 * مقایسه پایدار بدون حساسیت به ترتیب کلیدها.
 * تابع ماژول‌سطح تا فراخوانی بازگشتی بدون مشکل this کار کند.
 */
function stableStringify(obj) {
    if (obj === null || typeof obj !== "object") return JSON.stringify(obj);
    if (Array.isArray(obj)) return `[${obj.map(stableStringify).join(",")}]`;
    const keys = Object.keys(obj).sort();
    return `{${keys.map(k => `${JSON.stringify(k)}:${stableStringify(obj[k])}`).join(",")}}`;
}

/**
 * 🛠 سرویس مدیریت ام‌گذاری و نگاشت‌ها
 */
class MappingService {
    /**
     * @param {string} apiBaseUrl
     * @param {number|string} priceSourceId
     */
    constructor(apiBaseUrl, priceSourceId) {
        this.apiBaseUrl = apiBaseUrl;
        this.priceSourceId = priceSourceId;
    }

    async loadNameMapping(currentMapping = {}) {
        const url = `${this.apiBaseUrl}/api/metal-item-mappings/${this.priceSourceId}`;
        const response = await axios.get(
            url,
            {
                timeout: 15000,
                headers: {
                    "Accept": "application/json",
                    "X-Api-Secret": process.env.INTERNAL_API_SECRET
                }
            }
        );
        const newMapping = response.data;

        // مقایسه مستقل از ترتیب کلیدها → جلوگیری از restart کاذب
        const hasChanged = stableStringify(currentMapping) !== stableStringify(newMapping);

        return {
            mapping: newMapping,
            hasChanged: hasChanged && Object.keys(newMapping || {}).length > 0,
        };
    }
}

module.exports = MappingService;
