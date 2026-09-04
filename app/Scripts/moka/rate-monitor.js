require("dotenv").config();
const { ensureLoggedIn } = require("./auth-service");
const MappingService = require('./services/mapping.service');
const CardScraperService = require('./services/card-scraper.service');

// ═══════════════════════════════════════════════════════════════
// 🔧 متغیرهای محیطی
// ═══════════════════════════════════════════════════════════════
const PRICE_SOURCE_ID = parseInt(process.env.PRICE_SOURCE_ID, 10) || 1;
const NAME_MAPPING_RELOAD_INTERVAL_MS = parseInt(process.env.NAME_MAPPING_RELOAD_INTERVAL_MS, 10) || 120000;
const API_BASE_URL = process.env.API_BASE_URL || "https://api.zhikgold.ir";

const mappingService = new MappingService(API_BASE_URL, PRICE_SOURCE_ID);

let currentPage = null;

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

        setInterval(async () => {
            console.log("🔄 Reloading name mappings...");
            await loadNameMapping();
        }, NAME_MAPPING_RELOAD_INTERVAL_MS);

        await ensureLoggedIn(page);

        await page.exposeFunction("onCardsChanged", async (times) => {
            for (let idx = 0; idx < times.length; idx++) {
                if (!times[idx]) continue;

                cardScraperService.scrapeCard(page, idx, "observer");
            }
        });

        await page.exposeFunction("logToNode", (message) => {
            console.log("🔍 [Observer]:", message);
        });

        await page.evaluate(() => {
            if (window.__observerAttached) return;
            window.__observerAttached = true;

            let lastTimes = [];

            const container = document.querySelector("#ItemPricesList");
            if (!container) {
                console.warn("⚠️ #ItemPricesList not found for observer");
                return;
            }

            const observer = new MutationObserver(() => {
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
                subtree: true, // ✅ تغییرات در تمام فرزندان و node ها را هم رصد کن
                characterData: true, // ✅ تغییرات متن داخل node ها را رصد کن
                childList: true // ✅ رصد اضافه / حذف شدن عناصر
            });

            window.__priceObserver = observer;
        });

        console.log("✅ SelectedMetalPrice monitoring activated — Observer ready");
    } catch (err) {
        console.error("❌ General error in start:", err.message);
        throw err;
    }
}

/**
 * 🔁 ری‌استارت مانیتورینگ پس از تغییر mapping
 */
async function restartMonitoring() {
    console.log("🔁 Restarting monitoring...");

    cardScraperService.resetState();

    console.log("✅ Monitoring restarted with new mapping");
}


module.exports = { start };
