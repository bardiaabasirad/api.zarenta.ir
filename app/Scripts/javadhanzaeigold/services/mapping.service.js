require("dotenv").config();
const axios = require('axios');

/**
 * 🛠 سرویس مدیریت نام‌گذاری و نگاشت‌ها
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

    /**
     * 📥 دریافت آخرین نگاشت‌ها از سرور
     * @param {Object} currentMapping - مپینگ فعلی برای مقایسه
     * @returns {Promise<{mapping: Object, hasChanged: boolean}>}
     */
    async loadNameMapping(currentMapping = {}) {
        try {
            console.log("✅ load name mapping");

            const url = `${this.apiBaseUrl}/api/metal-item-mappings/${this.priceSourceId}`;
            const response = await axios.get(
                url,
                {
                    timeout: 15000,
                    "Accept": "application/json",
                    "X-Api-Secret": process.env.INTERNAL_API_SECRET
                }
            );

            const newMapping = response.data;

            // بررسی تغییرات برای ری‌استارت کردن مانیتورینگ در لایه بالاتر
            const hasChanged = JSON.stringify(currentMapping) !== JSON.stringify(newMapping);

            return {
                mapping: newMapping,
                hasChanged: hasChanged && Object.keys(newMapping).length > 0
            };
        } catch (error) {
            console.error("❌ [MappingService] Failed to load name mapping:", error.message);
            throw error;
        }
    }
}

module.exports = MappingService;
