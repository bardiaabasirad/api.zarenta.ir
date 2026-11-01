const fs = require("fs");
const { chromium } = require("playwright");
const { agentLog } = require("./utils/debug-agent.util");

let browser = null;
let context = null;
let mainPage = null;

const WINDOWS_CHROME_CANDIDATES = [
    "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
    "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe",
    process.env.LOCALAPPDATA
        ? `${process.env.LOCALAPPDATA}\\Google\\Chrome\\Application\\chrome.exe`
        : null,
].filter(Boolean);

const LINUX_CHROME_CANDIDATES = [
    "/usr/bin/google-chrome",
    "/usr/bin/google-chrome-stable",
    "/usr/bin/chromium",
    "/usr/bin/chromium-browser",
    "/snap/bin/chromium",
];

/**
 * مسیر مرورگر سیستمی را پیدا می‌کند تا نیازی به دانلود Chromium Playwright نباشد.
 */
function resolveSystemChromePath() {
    if (process.env.PLAYWRIGHT_CHROME_PATH) {
        return process.env.PLAYWRIGHT_CHROME_PATH;
    }

    const candidates =
        process.platform === "win32"
            ? WINDOWS_CHROME_CANDIDATES
            : LINUX_CHROME_CANDIDATES;

    return candidates.find((candidate) => fs.existsSync(candidate)) || null;
}

/**
 * 🧩 Initialize browser and create a persistent page
 */
async function initBrowser() {
    if (browser && mainPage && !mainPage.isClosed()) {
        return { browser, context, mainPage };
    }

    const isWindows = process.platform === "win32";
    const isVisibleMode =
        isWindows &&
        process.env.PLAYWRIGHT_HEADLESS !== "true" &&
        process.env.PUPPETEER_HEADLESS !== "true";

    console.log("🚀 Launching Playwright browser...");

    const launchOptions = {
        headless: !isVisibleMode,
        /*
         * Playwright به‌طور پیش‌فرض --enable-automation می‌گذارد؛
         * بعضی WAF/پروکسی‌ها همان را می‌بینند و TLS را قطع می‌کنند.
         */
        ignoreDefaultArgs: ["--enable-automation"],
        args: [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--disable-dev-shm-usage",
            "--disable-blink-features=AutomationControlled",
            "--disable-features=IsolateOrigins,site-per-process",
            ...(isVisibleMode ? ["--start-maximized"] : ["--disable-gpu"]),
        ],
    };

    const chromePath = resolveSystemChromePath();

    if (chromePath) {
        launchOptions.executablePath = chromePath;
        console.log(`🌐 Using system Chrome: ${chromePath}`);
    } else if (isWindows) {
        launchOptions.channel = "chrome";
        console.log("🌐 Using Playwright channel=chrome");
    } else {
        console.warn(
            "⚠️ System Chrome not found; falling back to Playwright Chromium. " +
                "If launch fails, set PLAYWRIGHT_CHROME_PATH."
        );
    }

    browser = await chromium.launch(launchOptions);

    browser.on("disconnected", () => {
        console.warn("⚠️ Browser disconnected!");
        // #region agent log
        agentLog(
            "playwright-service.js:disconnected",
            "Browser disconnected — refs nulled, process still alive",
            {
                pageWasClosed: mainPage ? mainPage.isClosed() : null,
                uptimeMs: Math.round(process.uptime() * 1000),
                willRecreate: false,
            },
            "A"
        );
        // #endregion
        browser = null;
        context = null;
        mainPage = null;
    });

    context = await browser.newContext({
        viewport: isVisibleMode ? null : { width: 1920, height: 1080 },
        ignoreHTTPSErrors: true,
        locale: "fa-IR",
        timezoneId: "Asia/Tehran",
        javaScriptEnabled: true,
        bypassCSP: true,
        extraHTTPHeaders: {
            "Accept-Language": "fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7",
        },
    });

    /*
     * navigator.webdriver را برای کاهش احتمال بلاک سمت سرور مخفی می‌کنیم.
     */
    await context.addInitScript(() => {
        Object.defineProperty(navigator, "webdriver", {
            get: () => undefined,
        });
    });

    mainPage = await context.newPage();
    mainPage.setDefaultNavigationTimeout(60000);
    mainPage.setDefaultTimeout(30000);

    mainPage.on("crash", () => {
        console.error("❌ Page crashed");
        // #region agent log
        agentLog(
            "playwright-service.js:crash",
            "Page crashed event",
            {},
            "A"
        );
        // #endregion
    });

    mainPage.on("pageerror", (err) => {
        console.error("❌ Page runtime error:", err.message);
    });

    return { browser, context, mainPage };
}

module.exports = {
    initBrowser,
};
