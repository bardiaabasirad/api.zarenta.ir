require("dotenv").config();

const { ensureLoggedIn } = require("./auth-service");
const MappingService = require("./services/mapping.service");
const CardScraperService = require("./services/card-scraper.service");

// ═══════════════════════════════════════════════════════════════
// 🔧 Environment configuration
// ═══════════════════════════════════════════════════════════════
const PRICE_SOURCE_ID = Number.parseInt(process.env.PRICE_SOURCE_ID, 10) || 1;
const API_BASE_URL = process.env.API_BASE_URL || "https://api.zhikgold.ir";

const MAPPING_RELOAD_INTERVAL_MS =
    Number(process.env.MAPPING_RELOAD_INTERVAL_MS) || 600_000;

// ═══════════════════════════════════════════════════════════════
// 🧠 Shared services and state
// ═══════════════════════════════════════════════════════════════
const mappingService = new MappingService(API_BASE_URL, PRICE_SOURCE_ID);

const cardScraperService = new CardScraperService(
    API_BASE_URL,
    PRICE_SOURCE_ID,
    () => nameMapping
);

/**
 * Mapping فعلی نام کارت‌ها به نرخ‌های موردنیاز.
 */
let nameMapping = {};

/**
 * وضعیت lifecycle مانیتور.
 */
let currentPage = null;
let isStarted = false;
let startPromise = null;
let stopPromise = null;

/**
 * وضعیت reload mapping.
 */
let mappingTimer = null;
let mappingLoopToken = 0;
let isReloading = false;
let mappingFailureCount = 0;
let isRestarting = false;

/**
 * وضعیت observer و صف پردازش scrape.
 *
 * observerToken با هر attach/stop افزایش می‌یابد؛
 * بنابراین callbackهای قدیمی که از DOM قبلی باقی مانده‌اند،
 * دیگر اجازه‌ی پردازش ندارند.
 */
let observerToken = 0;
let scrapeQueue = Promise.resolve();

/**
 * WeakSet باعث می‌شود exposeFunction برای یک Page بیش از یک‌بار
 * فراخوانی نشود. Puppeteer bindingهای exposeFunction را در navigation
 * نیز حفظ می‌کند، اما خود MutationObserver بعد از تغییر DOM باید
 * دوباره attach شود.
 */
const exposedPages = new WeakSet();

/**
 * 🔔 Hook برای اطلاع‌دادن فعالیت واقعی به Watchdog.
 */
let activityHook = null;

// ═══════════════════════════════════════════════════════════════
// 🗺️ Mapping management
// ═══════════════════════════════════════════════════════════════

/**
 * بارگذاری امن mappingها.
 *
 * اگر mapping تغییر کرده باشد، فقط cache و state اسکرپر reset می‌شود.
 * برای تغییر mapping لازم نیست observer را restart کنیم، چون callback
 * اسکرپر همیشه mapping جدید را از طریق getter دریافت می‌کند.
 */
async function reloadMappingSafe() {
    if (isReloading) {
        return;
    }

    isReloading = true;

    const startedAt = new Date().toISOString();

    try {
        const { mapping, hasChanged } =
            await mappingService.loadNameMapping(nameMapping);

        nameMapping = mapping || {};
        mappingFailureCount = 0;

        if (hasChanged) {
            console.log(
                `[${startedAt}] [mapping] changed → resetting scraper state`
            );

            await restartMonitoring();
        }
    } catch (error) {
        mappingFailureCount += 1;

        console.error(
            `[${startedAt}] [mapping] reload failed ` +
            `(#${mappingFailureCount}): ${error.message}`
        );
    } finally {
        isReloading = false;
    }
}

/**
 * شروع loop زمان‌بندی‌شده برای reload کردن mappingها.
 *
 * از setTimeout بازگشتی استفاده شده تا اجرای هم‌پوشان اتفاق نیفتد.
 * token از زنده‌شدن دوباره‌ی loop قبلی پس از stop جلوگیری می‌کند.
 */
function startMappingReloadLoop() {
    stopMappingReloadLoop();

    const currentLoopToken = mappingLoopToken;

    const tick = async () => {
        if (!isStarted || currentLoopToken !== mappingLoopToken) {
            return;
        }

        await reloadMappingSafe();

        if (!isStarted || currentLoopToken !== mappingLoopToken) {
            return;
        }

        mappingTimer = setTimeout(tick, MAPPING_RELOAD_INTERVAL_MS);
    };

    mappingTimer = setTimeout(tick, MAPPING_RELOAD_INTERVAL_MS);
}

/**
 * توقف کامل loop reload mapping.
 */
function stopMappingReloadLoop() {
    mappingLoopToken += 1;

    if (mappingTimer) {
        clearTimeout(mappingTimer);
        mappingTimer = null;
    }
}

/**
 * پس از تغییر mapping، hash/state قبلی اسکرپر را پاک می‌کند تا داده‌هایی
 * که اکنون mapping معتبر دارند، مجدداً امکان پردازش داشته باشند.
 */
async function restartMonitoring() {
    if (isRestarting) {
        console.log(
            `[${new Date().toISOString()}] [mapping] reset skipped ` +
            `(already in progress)`
        );

        return;
    }

    isRestarting = true;

    try {
        cardScraperService.resetState();

        console.log(
            `[${new Date().toISOString()}] [mapping] scraper state reset`
        );
    } catch (error) {
        console.error(`[mapping] scraper state reset failed: ${error.message}`);
    } finally {
        isRestarting = false;
    }
}

// ═══════════════════════════════════════════════════════════════
// 🔌 Node ↔ Browser callbacks
// ═══════════════════════════════════════════════════════════════

/**
 * پردازش eventهای دریافتی از MutationObserver به‌شکل ترتیبی.
 *
 * اجرای ترتیبی ضروری است، چون در یک mutation سنگین ممکن است چند callback
 * پشت‌سرهم ایجاد شود و scrape هم‌زمان کارت‌ها باعث ارسال تکراری یا race
 * condition شود.
 */
async function handleCardsChanged(payload = {}) {
    const {
        indexes = [],
        source = "observer",
        token,
    } = payload;

    /*
     * Event متعلق به observer قبلی است؛ مثلاً بازار بسته شده، DOM حذف شده
     * یا recovery انجام شده است. چنین eventی نباید روی DOM جدید اثر بگذارد.
     */
    if (token !== observerToken) {
        console.log(
            `ℹ️ [observer] ignored stale ${source} event ` +
            `(event token: ${token}, active token: ${observerToken})`
        );

        return;
    }

    if (!currentPage || currentPage.isClosed()) {
        console.log(`⚠️ [observer] ignored ${source} event: page is unavailable`);
        return;
    }

    const uniqueIndexes = [
        ...new Set(
            indexes.filter(
                (index) => Number.isInteger(index) && index >= 0
            )
        ),
    ];

    if (uniqueIndexes.length === 0) {
        return;
    }

    /*
     * این hook نشان می‌دهد observer واقعاً event دریافت کرده و مسیر scrape
     * فعال است. حتی اگر API مقصد موقتاً خطا دهد، page/observer سالم محسوب
     * می‌شود و watchdog نباید صرفاً به خاطر مشکل شبکه، browser را restart کند.
     */
    if (typeof activityHook === "function") {
        try {
            activityHook();
        } catch (error) {
            console.error(
                `⚠️ [activityHook] execution failed: ${error.message}`
            );
        }
    }

    for (const index of uniqueIndexes) {
        try {
            await cardScraperService.scrapeCard(
                currentPage,
                index,
                source
            );
        } catch (error) {
            console.error(
                `❌ [${source}] card[${index}] scrape failed: ${error.message}`
            );
        }
    }
}

/**
 * callbacks موردنیاز در Page Context را فقط یک بار expose می‌کند.
 */
async function exposeCallbacks(page) {
    if (!page || page.isClosed()) {
        throw new Error("Cannot expose callbacks: page is unavailable");
    }

    if (exposedPages.has(page)) {
        return;
    }

    try {
        await page.exposeFunction("onCardsChanged", async (payload) => {
            /*
             * صف باید حتی اگر یک batch قبلی fail شود، برای batch بعدی سالم بماند.
             */
            scrapeQueue = scrapeQueue
                .catch(() => undefined)
                .then(() => handleCardsChanged(payload));

            return scrapeQueue;
        });

        await page.exposeFunction("logToNode", (message) => {
            console.log(`🔍 [observer] ${message}`);
        });

        exposedPages.add(page);

        console.log("✅ [observer] callbacks exposed to page");
    } catch (error) {
        /*
         * اگر exposeFunction از قبل توسط اجرای قبلی ثبت شده باشد، آن را
         * به عنوان خطای fatal در نظر نمی‌گیریم؛ اما Page را در WeakSet نگه
         * می‌داریم تا دوباره exposeFunction صدا زده نشود.
         */
        if (error.message.includes("already exists")) {
            exposedPages.add(page);

            console.log("✅ [observer] callbacks already exposed to page");
            return;
        }

        throw error;
    }
}

// ═══════════════════════════════════════════════════════════════
// 👁️ MutationObserver lifecycle
// ═══════════════════════════════════════════════════════════════

/**
 * observer قبلی را از Page Context جدا می‌کند.
 *
 * اگر بازار بسته شود و HTML نرخ‌ها حذف شود، observer متصل به container قبلی
 * دیگر توانایی مشاهده‌ی container جدید را ندارد. پس در stop/recovery باید
 * reference قبلی را حتماً disconnect کنیم.
 */
async function disconnectObserver(page) {
    observerToken += 1;

    if (!page || page.isClosed()) {
        return;
    }

    try {
        await page.evaluate(() => {
            if (window.__priceObserver) {
                window.__priceObserver.disconnect();
            }

            window.__priceObserver = null;
            window.__priceObserverAttached = false;
            window.__priceObserverToken = null;
        });
    } catch (error) {
        /*
         * navigation وسط evaluate یا destruction صفحه نباید stop/recovery
         * را متوقف کند.
         */
        console.log(
            `⚠️ [observer] could not disconnect observer cleanly: ${error.message}`
        );
    }
}

/**
 * نصب observer روی container فعلی نرخ‌ها.
 *
 * @returns {Promise<boolean>}
 * true: observer با موفقیت روی #ItemPricesList نصب شد.
 * false: container فعلاً در صفحه نیست؛ معمولاً زمان بسته‌بودن بازار.
 */
async function attachObserver(page) {
    if (!page || page.isClosed()) {
        throw new Error("Cannot attach observer: page is unavailable");
    }

    currentPage = page;

    await exposeCallbacks(page);
    await disconnectObserver(page);

    const activeToken = ++observerToken;

    const result = await page.evaluate((token) => {
        const log = (message) => {
            if (typeof window.logToNode === "function") {
                window.logToNode(message);
            }
        };

        const container = document.querySelector("#ItemPricesList");

        /*
         * این اتفاق در ساعات بسته‌بودن بازار طبیعی است، خصوصاً وقتی کل
         * HTML نرخ‌ها از DOM حذف می‌شود. اینجا exception نمی‌دهیم تا
         * watchdog بتواند منتظر بازگشت market UI بماند.
         */
        if (!container) {
            window.__priceObserver = null;
            window.__priceObserverAttached = false;
            window.__priceObserverToken = null;

            log("ℹ️ #ItemPricesList is not present; observer is waiting for next start");

            return {
                attached: false,
                initialIndexes: [],
            };
        }

        const getCardUpdateTime = (card) => {
            return (
                card
                    .querySelector(".price-updateTime")
                    ?.textContent
                    ?.trim() || ""
            );
        };

        const getSnapshot = () => {
            return Array.from(container.querySelectorAll(".card")).map(
                (card) => getCardUpdateTime(card)
            );
        };

        let lastTimes = getSnapshot();

        const notifyNode = (indexes, source) => {
            if (
                indexes.length === 0 ||
                typeof window.onCardsChanged !== "function"
            ) {
                return;
            }

            /*
             * exposeFunction یک Promise برمی‌گرداند؛ MutationObserver نباید
             * منتظر آن بماند، اما rejection را برای جلوگیری از unhandled
             * rejection مدیریت می‌کنیم.
             */
            Promise.resolve(
                window.onCardsChanged({
                    indexes,
                    source,
                    token,
                })
            ).catch((error) => {
                log(`❌ Failed to deliver ${source} event: ${error.message}`);
            });
        };

        const observer = new MutationObserver(() => {
            /*
             * اگر پس از تغییر route یا DOM، این observer قدیمی مانده باشد،
             * از پردازش event جلوگیری می‌شود.
             */
            if (window.__priceObserverToken !== token) {
                return;
            }

            const cards = Array.from(container.querySelectorAll(".card"));
            const currentTimes = cards.map((card) => getCardUpdateTime(card));
            const changedIndexes = [];

            cards.forEach((card, index) => {
                /*
                 * کارت تازه‌اضافه‌شده، تغییر timestamp، یا تغییر تعداد کارت‌ها
                 * باید برای scrape ارسال شود.
                 */
                const isNewCard = index >= lastTimes.length;
                const updateTimeChanged =
                    currentTimes[index] !== lastTimes[index];

                if (isNewCard || updateTimeChanged) {
                    changedIndexes.push(index);
                }
            });

            lastTimes = currentTimes;

            notifyNode(changedIndexes, "observer");
        });

        observer.observe(container, {
            childList: true,
            subtree: true,
            characterData: true,
        });

        window.__priceObserver = observer;
        window.__priceObserverAttached = true;
        window.__priceObserverToken = token;

        /*
         * MutationObserver فقط تغییرات آینده را می‌بیند. با initial scrape
         * تمام کارت‌های موجود در لحظه‌ی attach، نرخ‌های پس از recovery یا
         * ابتدای شروع بازار نیز پردازش می‌شوند.
         */
        const initialIndexes = Array.from(
            container.querySelectorAll(".card"),
            (_, index) => index
        );

        notifyNode(initialIndexes, "initial");

        log(
            `✅ Observer attached to #ItemPricesList; ` +
            `initial cards: ${initialIndexes.length}`
        );

        return {
            attached: true,
            initialIndexes,
        };
    }, activeToken);

    if (!result.attached) {
        console.log(
            "ℹ️ [observer] rate list is currently unavailable; " +
            "no observer was attached"
        );

        return false;
    }

    console.log(
        `✅ [observer] attached successfully; ` +
        `initial cards queued: ${result.initialIndexes.length}`
    );

    return true;
}

/**
 * توقف کامل monitor:
 * - متوقف‌کردن loop mapping
 * - invalid کردن callbackهای observer قبلی
 * - disconnect کردن MutationObserver
 *
 * این متد باید قبل از recovery و قبل از restart شدن application اجرا شود.
 */
async function stop(page = currentPage) {
    if (stopPromise) {
        return stopPromise;
    }

    stopPromise = (async () => {
        isStarted = false;
        startPromise = null;

        stopMappingReloadLoop();
        await disconnectObserver(page);

        currentPage = null;

        console.log("🛑 Rate monitor stopped");
    })();

    try {
        await stopPromise;
    } finally {
        stopPromise = null;
    }
}

// ═══════════════════════════════════════════════════════════════
// 🚀 Monitor lifecycle
// ═══════════════════════════════════════════════════════════════

/**
 * شروع کامل مانیتورینگ نرخ‌ها.
 *
 * این متد idempotent است:
 * - اگر برای همان page قبلاً فعال باشد، observer را مجدداً نصب نمی‌کند.
 * - اگر page جدیدی وارد شود، instance قبلی ابتدا متوقف می‌شود.
 */
async function start(page) {
    if (!page || page.isClosed()) {
        throw new Error("Cannot start rate monitor: page is unavailable");
    }

    if (isStarted && currentPage === page) {
        console.log("ℹ️ Rate monitor is already started");
        return true;
    }

    if (startPromise) {
        return startPromise;
    }

    startPromise = (async () => {
        try {
            if (currentPage && currentPage !== page) {
                await stop(currentPage);
            }

            currentPage = page;

            console.log("📊 Starting rate monitor...");

            /*
             * برای استفاده‌ی مستقل از RateMonitor نیز login بررسی می‌شود.
             * در recovery، watchdog پیش‌تر این کار را انجام داده و این call
             * معمولاً بدون navigation اضافه برمی‌گردد.
             */
            await ensureLoggedIn(page);

            await reloadMappingSafe();

            /*
             * قبل از شروع timer، isStarted=true می‌شود تا callback tick
             * معتبر شناخته شود.
             */
            isStarted = true;
            startMappingReloadLoop();

            const attached = await attachObserver(page);

            if (!attached) {
                /*
                 * این حالت هنگام بسته‌بودن بازار طبیعی است. Watchdog وقتی
                 * #ItemPricesList یا آیتم‌ها دوباره ظاهر شوند، stop/start
                 * یا recovery را اجرا می‌کند تا observer روی DOM جدید attach شود.
                 */
                console.log(
                    "ℹ️ Rate monitor started without observer: " +
                    "rate list is not currently available"
                );

                return false;
            }

            console.log(
                "✅ SelectedMetalPrice monitoring activated — Observer ready"
            );

            return true;
        } catch (error) {
            isStarted = false;
            stopMappingReloadLoop();

            console.error(`❌ General error in rate monitor start: ${error.message}`);

            throw error;
        } finally {
            startPromise = null;
        }
    })();

    return startPromise;
}

// ═══════════════════════════════════════════════════════════════
// 🔔 Watchdog activity integration
// ═══════════════════════════════════════════════════════════════

function registerActivityHook(fn) {
    if (typeof fn !== "function") {
        throw new TypeError("Activity hook must be a function");
    }

    activityHook = fn;

    console.log("🔗 [activityHook] registered");
}

module.exports = {
    start,
    stop,
    attachObserver,
    markActivity: registerActivityHook,
};
