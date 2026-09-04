require("dotenv").config();
const { ensureLoggedIn } = require("./auth-service");
const MappingService = require('./services/mapping.service');
const CardScraperService = require('./services/card-scraper.service');
const {currentTime} = require("./utils/current-time.util");

// ═══════════════════════════════════════════════════════════════
// 🔧 متغیرهای محیطی
// ═══════════════════════════════════════════════════════════════
const PRICE_SOURCE_ID = parseInt(process.env.PRICE_SOURCE_ID, 10) || 1;
const NAME_MAPPING_RELOAD_INTERVAL_MS = parseInt(process.env.NAME_MAPPING_RELOAD_INTERVAL_MS, 10) || 120000;
const API_BASE_URL = process.env.API_BASE_URL || "https://api.zhikgold.ir";

const mappingService = new MappingService(API_BASE_URL, PRICE_SOURCE_ID);

let currentPage = null;
let mappingReloadTimer = null;
let observerHealthTimer = null;

/**
 * 🗺️ nameMapping - بارگذاری از دیتابیس
 */
let nameMapping = {};

// سرویس جدید برای اسکرپ کارت‌ها
const cardScraperService = new CardScraperService(
    API_BASE_URL,
    PRICE_SOURCE_ID,
    () => nameMapping
);

/**
 * 📥 بارگذاری nameMapping از API
 */
async function loadNameMapping() {
    try {
        const { mapping, hasChanged } = await mappingService.loadNameMapping(nameMapping);

        if (hasChanged) {
            console.log("⚠️ Name mapping changed - restarting monitoring");
            nameMapping = mapping;
            await restartMonitoring();
        } else {
            nameMapping = mapping;
        }
    } catch (error) {
        console.error("❌ [RateMonitor] Failed to load name mapping (ignored):", error.message);
    }
}

/**
 * 🚀 Start rate monitoring
 */
async function start(page) {
    try {
        currentPage = page;

        console.log("📊 Starting rate monitor...");

        await loadNameMapping();

        // به‌جای setInterval بدون مدیریت
        if (mappingReloadTimer) {
            clearInterval(mappingReloadTimer);
            mappingReloadTimer = null;
        }

        mappingReloadTimer = setInterval(async () => {
            console.log(`🔄 [${currentTime()}] Reloading name mappings...`);
            await loadNameMapping();
        }, NAME_MAPPING_RELOAD_INTERVAL_MS);

        await ensureLoggedIn(page);

        await safeExpose(page, "onCardsChanged", async (times) => {
            for (let idx = 0; idx < times.length; idx++) {
                if (!times[idx]) continue;
                cardScraperService.scrapeCard(page, idx, "observer");
            }
        });

        await safeExpose(page, "logToNode", (message) => {
            console.log("🔍 [Observer]:", message);
        });

        await attachObserver(page);

        if (observerHealthTimer) clearInterval(observerHealthTimer);
        observerHealthTimer = setInterval(() => logObserverHealth(currentPage), 10000);

        console.log("✅ SelectedMetalPrice monitoring activated — Observer ready");
    } catch (err) {
        console.error("❌ General error in start:", err.message);
        throw err;
    }
}

async function safeExpose(page, name, fn) {
    try {
        await page.exposeFunction(name, fn);
    } catch (err) {
        if (err.message.includes("already exists")) {
            // binding از قبل در این context ثبت شده — امن است
            return;
        }
        throw err;
    }
}

/**
 * 🔁 ری‌استارت مانیتورینگ پس از تغییر mapping
 */
async function restartMonitoring() {
    console.log(`🔁 [${currentTime()}] Restarting monitoring (mapping changed)...`);
    if (!currentPage) {
        console.warn("⚠️ restartMonitoring: no currentPage, skipping");
        return;
    }
    await resyncMonitoring(currentPage);
    console.log(`✅ [${currentTime()}] Monitoring restarted with new mapping`);
}


/**
 * 🔭 نصب MutationObserver روی کانتینر فعلی #ItemPricesList
 */
async function attachObserver(page) {
    await page.evaluate(() => {
        const container = document.querySelector("#ItemPricesList");
        if (!container) {
            console.warn("⚠️ #ItemPricesList not found for observer");
            window.__observerAttached = false; // اجازهٔ تلاش مجدد بعدی
            return;
        }

        // ── باگ‌فیکس ۱: اگر قبلاً observer روی همین نودِ زنده نشسته، کاری نکن.
        //    اما اگر نود عوض شده یا observer مرده، پاکسازی و نصب مجدد.
        const sameLiveNode =
            window.__observerAttached &&
            window.__priceObserver &&
            window.__observedContainer === container &&
            document.contains(container);

        if (sameLiveNode) {
            return; // واقعاً سالم است، no-op
        }

        // ── باگ‌فیکس ۲: observer قدیمی را قبل از نصب جدید قطع کن
        try {
            if (window.__priceObserver) {
                window.__priceObserver.disconnect();
            }
        } catch (e) {}
        window.__priceObserver = null;
        window.__observerAttached = false;

        // ── متغیرهای جدید: نودِ تحت نظر + زمان آخرین fire
        window.__observedContainer = container;
        window.__observerLastFire = Date.now();
        window.__observerFireCount = 0;

        let lastTimes = [];

        const observer = new MutationObserver(() => {
            // heartbeat: ثبت آخرین fire
            window.__observerLastFire = Date.now();
            window.__observerFireCount = (window.__observerFireCount || 0) + 1;

            const cards = container.querySelectorAll(".card");
            const times = [];

            cards.forEach((card, idx) => {
                const t = card.querySelector(".price-updateTime")?.textContent.trim() || null;

                if (t && t !== lastTimes[idx]) {
                    times[idx] = t;
                } else {
                    times[idx] = null;
                }
            });

            lastTimes = Array.from(cards).map(c =>
                c.querySelector(".price-updateTime")?.textContent.trim()
            );

            if (times.some(Boolean) && typeof window.onCardsChanged === "function") {
                window.onCardsChanged(times);
            }
        });

        observer.observe(container, {
            subtree: true,
            characterData: true,
            childList: true
        });

        window.__priceObserver = observer;
        window.__observerAttached = true;

        if (typeof window.logToNode === "function") {
            window.logToNode("observer attached to #ItemPricesList");
        }
    });
}
/**
 * 🔁 اتصال مجدد observer و اسکرپ اجباری پس از برگشت آیتم‌ها
 */
async function resyncMonitoring(page) {
    console.log("🔁 Resyncing monitoring after items returned...");

    // 1) دیتای dedup را پاک کن تا نرخ‌های برگشتی دوباره ارسال شوند
    cardScraperService.resetState();
    if (typeof cardScraperService.clearInFlight === "function") {
        cardScraperService.clearInFlight();
    }

    // 2) observer قدیمی را قطع و روی node فعلی دوباره نصب کن
    await page.evaluate(() => {
        try {
            if (window.__priceObserver) {
                window.__priceObserver.disconnect();
                window.__priceObserver = null;
            }
        } catch (e) {}
        window.__observerAttached = false; // اجازهٔ نصب مجدد
    });

    // 3) همان منطق نصب observer را دوباره اجرا کن (start را دوباره صدا نزن تا
    //    setInterval mapping و exposeFunction تکرار نشوند؛ فقط بخش observer)
    await attachObserver(page);

    // 4) یک اسکرپ اجباری همهٔ کارت‌ها، مستقل از observer
    const count = await page.evaluate(() => {
        const c = document.querySelector("#ItemPricesList");
        return c ? c.querySelectorAll(".card").length : 0;
    });

    for (let idx = 0; idx < count; idx++) {
        await cardScraperService.scrapeCard(page, idx, "resync");
    }

    console.log(`✅ Resync done — forced scrape of ${count} card(s)`);
}

async function logObserverHealth(page) {
    try {
        const info = await page.evaluate(() => {
            const container = document.querySelector("#ItemPricesList");
            return {
                attached: !!window.__observerAttached,
                hasObserver: !!window.__priceObserver,
                containerExists: !!container,
                sameNode: container && window.__observedContainer === container,
                connected: window.__observedContainer ? window.__observedContainer.isConnected : false,
                fireCount: window.__observerFireCount || 0,
                lastFireAgoMs: window.__observerLastFire ? (Date.now() - window.__observerLastFire) : null,
                cards: container ? container.querySelectorAll(".card").length : 0
            };
        });

        console.log(`💓 [${currentTime()}] ObserverHealth: attached=${info.attached} ` +
            `hasObs=${info.hasObserver} containerExists=${info.containerExists} ` +
            `sameNode=${info.sameNode} connected=${info.connected} ` +
            `fires=${info.fireCount} lastFire=${info.lastFireAgoMs}ms cards=${info.cards}`);

        // نود جایگزین شده یا observer روی نود detached مانده → خودترمیمی
        if (info.containerExists && (!info.sameNode || !info.connected)) {
            console.warn(`⚠️ [${currentTime()}] Observed node stale/replaced — auto resync`);
            await resyncMonitoring(page);
        }
    } catch (e) {
        console.error(`❌ ObserverHealth check failed: ${e.message}`);
    }
}

module.exports = { start, resyncMonitoring };
