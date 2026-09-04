require("dotenv").config();
const axios = require("axios");

/**
 * نرمال‌سازی یک شناسه‌ی metal item.
 * شناسه‌ها در mapping به صورت string استاندارد می‌شوند.
 */
function normalizeItemId(value) {
    if (typeof value === "number") {
        if (!Number.isSafeInteger(value) || value < 0) {
            return null;
        }
        return String(value);
    }

    if (typeof value === "string") {
        const normalized = value.trim();
        if (!normalized || !/^\d+$/.test(normalized)) {
            return null;
        }
        return normalized;
    }

    return null;
}

/**
 * نرمال‌سازی mapping و حذف ردیف‌های نامعتبر یا تکراری
 *
 * ورودی مورد انتظار:
 * {
 *   "آبشده نقد فردا": [12, 13]
 * }
 */
function normalizeMapping(rawMapping) {
    if (
        rawMapping === null ||
        typeof rawMapping !== "object" ||
        Array.isArray(rawMapping)
    ) {
        throw new Error("Invalid mapping response: expected an object");
    }

    const normalizedMapping = {};

    for (const [rawName, rawIds] of Object.entries(rawMapping)) {
        const name = typeof rawName === "string" ? rawName.trim() : "";
        if (!name) {
            continue;
        }

        if (!Array.isArray(rawIds)) {
            throw new Error(
                `Invalid mapping entry for "${name}": expected an array`
            );
        }

        const ids = rawIds.map(normalizeItemId).filter(Boolean);

        // حذف duplicateها و بی‌اثر کردن ترتیب آرایه با مرتب‌سازی صعودی
        const uniqueSortedIds = [...new Set(ids)].sort((a, b) => {
            const numberA = Number(a);
            const numberB = Number(b);
            return numberA - numberB || a.localeCompare(b);
        });

        // اگر آرایه‌ای ورودی داشته ولی هیچ شناسه معتبری درونش نبوده، خطا صادر می‌شود
        if (rawIds.length > 0 && uniqueSortedIds.length === 0) {
            throw new Error(
                `Invalid mapping entry for "${name}": no valid item IDs`
            );
        }

        if (uniqueSortedIds.length > 0) {
            normalizedMapping[name] = uniqueSortedIds;
        }
    }

    return normalizedMapping;
}

/**
 * تولید امضای پایدار از آبجکت جهت تشخیص دقیق تغییرات
 */
function stableStringify(value) {
    if (value === null || typeof value !== "object") {
        return JSON.stringify(value);
    }

    if (Array.isArray(value)) {
        return `[${value.map(stableStringify).join(",")}]`;
    }

    const keys = Object.keys(value).sort();
    return `{${keys
        .map((key) => `${JSON.stringify(key)}:${stableStringify(value[key])}`)
        .join(",")}}`;
}

function mappingsEqual(left, right) {
    return stableStringify(left) === stableStringify(right);
}

/**
 * 🛠 سرویس مدیریت نگاشت‌ها و تطبیق نام محصولات به شناسه‌های فلزات
 */
class MappingService {
    /**
     * @param {string} apiBaseUrl
     * @param {number|string} priceSourceId
     */
    constructor(apiBaseUrl, priceSourceId) {
        if (!apiBaseUrl || typeof apiBaseUrl !== "string") {
            throw new TypeError("apiBaseUrl must be a non-empty string");
        }

        if (
            priceSourceId === undefined ||
            priceSourceId === null ||
            String(priceSourceId).trim() === ""
        ) {
            throw new TypeError("priceSourceId is required");
        }

        this.apiBaseUrl = apiBaseUrl.replace(/\/+$/, "");
        this.priceSourceId = String(priceSourceId).trim();
        this.timeoutMs = 15000;
    }

    /**
     * دریافت و اعتبارسنجی زنده نگاشت‌ها از سرور
     * @param {Object} currentMapping - نگاشت فعلی ذخیره‌شده در رم
     */
    async loadNameMapping(currentMapping = {}) {
        const url = `${this.apiBaseUrl}/api/metal-item-mappings/${encodeURIComponent(this.priceSourceId)}`;

        let response;
        try {
            response = await axios.get(url, {
                timeout: this.timeoutMs,
                headers: {
                    Accept: "application/json",
                    "X-Api-Secret": process.env.INTERNAL_API_SECRET,
                },
                validateStatus: (status) => status >= 200 && status < 300,
            });
        } catch (error) {
            const status = error?.response?.status;
            const detail = status
                ? `HTTP ${status}`
                : error?.code || error?.message || "unknown error";

            throw new Error(`Mapping request failed: ${detail}`);
        }

        if (!response || typeof response.data === "undefined") {
            throw new Error("Mapping request returned an empty response");
        }

        let normalizedCurrent;
        try {
            normalizedCurrent = normalizeMapping(currentMapping || {});
        } catch (error) {
            console.warn(
                `[MappingService] Invalid current mapping ignored: ${error.message}`
            );
            normalizedCurrent = {};
        }

        const normalizedNew = normalizeMapping(response.data);

        // جلوگیری از حذف نگاشت‌های فعلی در صورت فرستاده شدن نگاشت خالی از سمت سرور
        if (
            Object.keys(normalizedNew).length === 0 &&
            Object.keys(normalizedCurrent).length > 0
        ) {
            return {
                mapping: normalizedCurrent,
                hasChanged: false,
            };
        }

        return {
            mapping: normalizedNew,
            hasChanged: !mappingsEqual(normalizedCurrent, normalizedNew),
        };
    }
}

module.exports = MappingService;
