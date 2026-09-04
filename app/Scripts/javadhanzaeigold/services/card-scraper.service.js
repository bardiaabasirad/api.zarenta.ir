const { generateRequestHash } = require("../utils/hash.util");
const { isDiffMoreThan } = require("../utils/validation.util");
const RateService = require("./rate.service");

/**
 * ✅ Ultra-fast state
 */

// 🔒 Per-card promise chain (بدون spin-wait)
const cardQueue = new Map(); // cardIndex -> Promise

// 📝 Set برای ذخیره درخواست‌های ارسال شده (محافظت نهایی)
const sentRequestsCache = new Set();
const CACHE_MAX_SIZE = 2000;

// 🔑 آخرین نرخ ارسال‌شده برای هر محصول (برای فیلتر کردن noise)
let lastSentRates = {}; // key -> { buy, sell }

// 🔑 آخرین uniqueKey ارسال شده برای هر محصول
let lastSentUniqueKey = {}; // key -> 'key_buy_sell_time'

// 🔐 hash درخواست‌های در حال پردازش
const inFlightRequests = new Map();

// 🧹 پاکسازی cache اگر بیش از حد بزرگ شد (سریع و سبک)
function cleanupCache() {
    if (sentRequestsCache.size <= CACHE_MAX_SIZE) return;

    // نصف قدیمی‌ها رو حذف می‌کنیم
    const targetRemove = Math.floor(CACHE_MAX_SIZE / 2);
    let i = 0;
    for (const v of sentRequestsCache) {
        sentRequestsCache.delete(v);
        if (++i >= targetRemove) break;
    }
}

// 🧹 پاکسازی درخواست‌های قدیمی (safety cleanup)
setInterval(() => {
    const now = Date.now();
    const TIMEOUT = 30000; // 30s

    for (const [hash, data] of inFlightRequests.entries()) {
        if (now - data.timestamp > TIMEOUT) {
            console.warn(`⚠️ Cleaning up stale request: [${data.trigger}] card[${data.cardIndex}]`);
            inFlightRequests.delete(hash);
        }
    }
}, 15000);

// اجرای ترتیبی per card بدون sleep/lock
function enqueueCard(cardIndex, fn) {
    const prev = cardQueue.get(cardIndex) || Promise.resolve();
    const next = prev
        .catch(() => {}) // خطای قبلی صف را نشکند
        .then(fn)
        .finally(() => {
            // اگر همین promise هنوز آخرین است، پاکش کن
            if (cardQueue.get(cardIndex) === next) cardQueue.delete(cardIndex);
        });

    cardQueue.set(cardIndex, next);
    return next;
}

class CardScraperService {
    constructor(apiBaseUrl, priceSourceId, getNameMapping) {
        this.rateService = new RateService(apiBaseUrl, priceSourceId);
        this.getNameMapping = getNameMapping;
        this.diffThresholdPercent = 0.5;
    }

    /**
     * 🧩 Scrape + send for one card index (Ultra-low latency)
     */
    scrapeCard(page, productId, trigger = "manual") {
        return enqueueCard(productId, async () => {
            try {
                const data = await page.evaluate((pid) => {
                    const titleEl = document.querySelector(`#product-card-title-${pid}`);
                    const buyEl = document.querySelector(`#product-${pid}-buy`);
                    const sellEl = document.querySelector(`#product-${pid}-sell`);
                    const timeEl = document.querySelector('#last-update span');

                    if (!titleEl || !buyEl || !sellEl) return null;

                    const parsePrice = (text) => {
                        const clean = text.replace(/,/g, '').trim();
                        return (clean === "نداریم" || isNaN(clean)) ? null : parseInt(clean);
                    };

                    return {
                        name: titleEl.innerText.trim(),
                        buy: parsePrice(buyEl.innerText),
                        sell: parsePrice(sellEl.innerText),
                        time: timeEl ? timeEl.innerText.trim() : new Date().toLocaleTimeString('fa-IR')
                    };
                }, productId);

                if (!data) return;

                const nameMapping = this.getNameMapping();
                const keys = nameMapping[data.name];
                if (!keys || !Array.isArray(keys)) return;

                // منطق ارسال همانند قبل...
                const formattedData = [];
                const touched = [];

                for (const key of keys) {
                    const uniqueKey = `${key}_${data.buy}_${data.sell}_${data.time}`;

                    if (lastSentUniqueKey[key] === uniqueKey || sentRequestsCache.has(uniqueKey)) continue;

                    // ✅ فیلتر ۱: اگر buy و sell دقیقاً تغییر نکرده، skip
                    const prev = lastSentRates[key];
                    if (prev && prev.buy === data.buy && prev.sell === data.sell) continue;

                    // ✅ فیلتر ۲: اگر اختلاف بیشتر از threshold باشد (جهش ناگهانی)، skip
                    if (prev) {
                        const buyHasBigDiff =
                            (!data.buy || !prev.buy)
                                ? (data.buy === prev.buy)
                                : isDiffMoreThan(prev.buy, data.buy, this.diffThresholdPercent);

                        const sellHasBigDiff =
                            (!data.sell || !prev.sell)
                                ? (data.sell === prev.sell)
                                : isDiffMoreThan(prev.sell, data.sell, this.diffThresholdPercent);

                        if (buyHasBigDiff || sellHasBigDiff) {
                            console.warn(`⚠️ [key=${key}] price jump detected, skipping. prev=(${prev.buy}/${prev.sell}) new=(${data.buy}/${data.sell})`);
                            continue;
                        }
                    }

                    const timeForServer = data.time.split('ساعت ')[1];

                    formattedData.push({
                        buy: data.sell,
                        sell: data.buy,
                        time: timeForServer,
                        metal_item_id: key
                    });

                    touched.push({ key, uniqueKey, buy: data.buy, sell: data.sell });
                }

                if (formattedData.length === 0) return;

                const wasSent = await this.rateService.sendRateToServer({
                    formattedData,
                    productId, // اینجا productId را پاس می‌دهیم
                    trigger,
                    generateHash: generateRequestHash,
                    inFlightRequests
                });

                if (wasSent) {
                    for (const t of touched) {
                        lastSentRates[t.key] = { buy: t.buy, sell: t.sell };
                        lastSentUniqueKey[t.key] = t.uniqueKey;
                        sentRequestsCache.add(t.uniqueKey);
                    }
                    cleanupCache();
                }
            } catch (err) {
                console.error(`❌ scrapeCard[${productId}] failed: ${err.message}`);
            }
        });
    }

    /**
     * 🧹 ریست state ها هنگام تغییر mapping
     */
    resetState() {
        lastSentRates = {};
        lastSentUniqueKey = {};
        sentRequestsCache.clear();
        // inFlightRequests intentionally left as-is (cleanup timer handles it)
    }
}

module.exports = CardScraperService;
