const puppeteer = require("puppeteer");

let browser = null;
let mainPage = null;

/**
 * 🧩 Initialize browser and create a persistent page
 */
async function initBrowser() {
    if (browser && mainPage && !mainPage.isClosed()) {
        return { browser, mainPage };
    }

    const isWindows = process.platform === "win32";
    const isVisibleMode = isWindows && process.env.PUPPETEER_HEADLESS !== "true";

    console.log("🚀 Launching browser...");

    browser = await puppeteer.launch({
        headless: isVisibleMode ? false : "new",
        executablePath: isWindows
            ? "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe"
            : undefined,
        args: [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--disable-dev-shm-usage",
            "--disable-gpu",
            ...(isVisibleMode ? ["--start-maximized"] : []),
        ],
    });

    browser.on("disconnected", () => {
        console.warn("⚠️ Browser disconnected!");
        browser = null;
        mainPage = null;
    });

    mainPage = await browser.newPage();

    if (isVisibleMode) {
        await mainPage.setViewport({
            width: 1920,
            height: 1080,
        });
    }

    mainPage.on("error", (err) => {
        console.error("❌ Page crashed:", err.message);
    });

    mainPage.on("pageerror", (err) => {
        console.error("❌ Page runtime error:", err.message);
    });

    return { browser, mainPage };
}

module.exports = {
    initBrowser
};
