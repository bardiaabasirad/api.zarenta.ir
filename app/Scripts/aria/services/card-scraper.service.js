const { ensureLoggedIn } = require("../auth-service");
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

// 🔑 آخرین نرخ ارسال‌شده برای هر محصول (برای فیلتر کردن تکراری‌ها و نوسان‌های نامتعارف)
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
            console.warn(
                `⚠️ Cleaning up stale request: [${data.trigger}] card[${data.cardIndex}]`
            );
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

        // آستانه تغییر معنی‌دار (فقط همین validation می‌ماند)
        this.diffThresholdPercent = 0.5;
        this.ensureLoginOnEachScrape = false; // سریع‌ترین حالت
    }

    /**
     * 🧩 Scrape + send for one card index (Ultra-low latency)
     */
    scrapeCard(page, cardIndex, trigger = "manual") {
        return enqueueCard(cardIndex, async () => {
            try {
                if (this.ensureLoginOnEachScrape) {
                    await ensureLoggedIn(page);
                }

                const section = await page.evaluate((idx) => {
                    const card = document.querySelectorAll("#ItemPricesList .card")[idx];
                    if (!card) return null;

                    const time =
                        card.querySelector(".price-updateTime")?.textContent?.trim() ||
                        null;
                    const rows = card.querySelectorAll(".row.border-bottom");

                    const items = [];
                    for (let i = 0; i < rows.length; i++) {
                        const row = rows[i];

                        const name =
                            row.querySelector(".col-4.text-start span")?.textContent?.trim() ||
                            null;
                        if (!name) continue;

                        const buyText =
                            row.querySelector(".highlight-buy-price span.text-success span")?.textContent?.trim() ||
                            null;
                        const sellText =
                            row.querySelector(".highlight-sell-price span.text-danger span")?.textContent?.trim() ||
                            null;

                        // طبق بیزنس، مقادیر buy و sell جابه‌جا نگاشت می‌شوند:
                        const buy = sellText ? sellText.replace(/,/g, "") : null;
                        const sell = buyText ? buyText.replace(/,/g, "") : null;

                        if (buy || sell) items.push({ name, buy, sell });
                    }

                    return { time, items };
                }, cardIndex);

                if (!section || !section.items || section.items.length === 0) return;

                const nameMapping = this.getNameMapping();
                if (!nameMapping) return;

                const formattedData = [];
                const touched = []; // برای update state بعد از ارسال موفق

                for (let i = 0; i < section.items.length; i++) {
                    const item = section.items[i];
                    const keys = nameMapping[item.name];

                    if (!keys || !Array.isArray(keys) || keys.length === 0) continue;

                    for (let k = 0; k < keys.length; k++) {
                        const key = keys[k];

                        // uniqueKey سریع و ساده
                        const uniqueKey = `${key}_${item.buy ?? ""}_${item.sell ?? ""}_${section.time ?? ""}`;

                        // ۱. جلوگیری از پردازش داده کاملاً یکسان با بررسی کش و uniqueKey
                        if (lastSentUniqueKey[key] === uniqueKey) continue;
                        if (sentRequestsCache.has(uniqueKey)) continue;

                        const prev = lastSentRates[key];

                        /*
                         * ۲. Row-level Deduplication برای مانیتورینگ زنده (observer):
                         * اگر این اجرای اسکرپ به دلیل تغییر در بقیه بخش‌های کارت فعال شده
                         * اما قیمت خرید و فروش این محصول خاص تغییری نسبت به آخرین ارسال موفق نداشته است،
                         * از ارسال مجدد آن جلوگیری می‌شود.
                         */
                        if (trigger !== "initial" && prev) {
                            const isBuyEqual = prev.buy === item.buy;
                            const isSellEqual = prev.sell === item.sell;

                            if (isBuyEqual && isSellEqual) {
                                continue;
                            }
                        }

                        // ۳. فیلتر کردن تغییر قیمت‌های ناگهانی و نامتعارف (noise/error)
                        if (prev) {
                            const buyHasBigDifferent =
                                !item.buy || !prev.buy
                                    ? item.buy === prev.buy
                                    : isDiffMoreThan(
                                        prev.buy,
                                        item.buy,
                                        this.diffThresholdPercent
                                    );

                            const sellHasBigDifferent =
                                !item.sell || !prev.sell
                                    ? item.sell === prev.sell
                                    : isDiffMoreThan(
                                        prev.sell,
                                        item.sell,
                                        this.diffThresholdPercent
                                    );

                            if (buyHasBigDifferent || sellHasBigDifferent) {
                                console.warn(
                                    `⚠️ Price change too high, skipped: Key=${key} | ` +
                                    `Prev: B=${prev.buy}/S=${prev.sell} | ` +
                                    `Curr: B=${item.buy}/S=${item.sell}`
                                );
                                continue;
                            }
                        }

                        formattedData.push({
                            buy: item.buy,
                            sell: item.sell,
                            time: section.time,
                            metal_item_id: key,
                        });

                        touched.push({
                            key,
                            uniqueKey,
                            buy: item.buy,
                            sell: item.sell,
                        });
                    }
                }

                if (formattedData.length === 0) return;

                const wasSent = await this.rateService.sendRateToServer({
                    formattedData,
                    cardIndex,
                    trigger,
                    generateHash: generateRequestHash,
                    inFlightRequests,
                });

                if (!wasSent) return;

                // update state (فقط بعد از ارسال موفق)
                for (let i = 0; i < touched.length; i++) {
                    const t = touched[i];
                    lastSentRates[t.key] = { buy: t.buy, sell: t.sell };
                    lastSentUniqueKey[t.key] = t.uniqueKey;
                    sentRequestsCache.add(t.uniqueKey);
                }

                cleanupCache();

                // Hook برای بازنشانی تایمر فعالیت در watchdog
                page.evaluate(() => {
                    if (typeof window.onCardsChangedHook === "function") {
                        window.onCardsChangedHook();
                    }
                }).catch(() => {});
            } catch (err) {
                console.error(`❌ scrapeCard[${cardIndex}] failed: ${err.message}`);
            }
        });
    }

    /**
     * 🧹 ریست کامل state ها هنگام تغییر mapping یا شروع مجدد مانیتور
     */
    resetState() {
        lastSentRates = {};
        lastSentUniqueKey = {};
        sentRequestsCache.clear();
        console.log("🧹 Scraper state has been completely reset");
    }
}

module.exports = CardScraperService;
