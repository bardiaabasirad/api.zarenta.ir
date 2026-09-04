require("dotenv").config();
const { initBrowser } = require("./puppeteer-service");
const { login } = require("./auth-service");
const rateMonitor = require("./rate-monitor");
const WatchdogService = require("./services/watchdog.service");
const logger = require("./utils/logger.util");

(async () => {
    try {
        const { mainPage } = await initBrowser();

        // 1. Authentication
        await login(mainPage, {
            username: process.env.PUPPETEER_USER,
            password: process.env.PUPPETEER_PASS,
            loginUrl: process.env.PUPPETEER_URL,
        });

        // 2. Setup Watchdog
        const watchdog = new WatchdogService(mainPage, {
            timeout: process.env.WATCHDOG_OBSERVER_TIMEOUT_MS,
            interval: process.env.WATCHDOG_INTERVAL_MS,
            usePm2: true,
            noItemTimeout: 3600000,
            rateMonitor: rateMonitor,
            freezeTimeout: process.env.FREEZE_TIMEOUT
        });

        // 3. Ignition
        watchdog.start();

    } catch (err) {
        logger.log(`❌ Fatal Entry Error: ${err.stack}`);
        process.exit(1);
    }
})();
