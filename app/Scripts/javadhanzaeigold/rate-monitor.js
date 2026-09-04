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

        await page.exposeFunction("onCardsChanged", async (productIds) => {
            for (const productId of productIds) {
                if (!productId) continue;
                // چون در سرویس فعلی `cardIndex` می‌گیرد، اینجا productId را پاس می‌دهیم
                // توجه: اگر نام متد در سرویس شما cardIndex است، مشکلی ندارد چون ID محصول الان نقش ایندکس را بازی می‌کند
                cardScraperService.scrapeCard(page, productId, "observer");
            }
        });

        await page.exposeFunction("logToNode", (message) => {
            console.log("🔍 [Observer]:", message);
        });

        await page.evaluate(() => {
            if (window.__observerAttached) return;
            window.__observerAttached = true;

            const DEBOUNCE_DELAY = 500; // کمی بیشتر برای اطمینان از پایان کامل بروزرسانی صفحه
            let timer = null;

            // انتخاب المانی که تغییر زمان در آن رخ می‌دهد
            const timeContainer = document.querySelector("#last-update span");
            if (!timeContainer) {
                console.warn("⚠️ #last-update span not found!");
                return;
            }

            const observer = new MutationObserver(() => {
                // با هر تغییر در تگ زمان، یک debounce شروع می‌شود
                if (timer) clearTimeout(timer);

                timer = setTimeout(async () => {
                    // دریافت تمام IDهای محصولات موجود در صفحه
                    const productCards = document.querySelectorAll('[id^="product-card-"]');
                    const productIds = Array.from(productCards).map(el =>
                        el.id.replace('product-card-', '')
                    );

                    // ارسال به Node.js برای بررسی و اسکرپ مجدد
                    if (productIds.length > 0) {
                        window.onCardsChanged(productIds);
                    }
                }, DEBOUNCE_DELAY);
            });

            // رصد تغییرات متن داخل span
            observer.observe(timeContainer, {
                subtree: true,
                characterData: true,
                childList: true
            });
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
