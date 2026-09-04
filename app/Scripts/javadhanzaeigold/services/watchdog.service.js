const logger = require("../utils/logger.util");
const { ensureLoggedIn } = require("../auth-service");

class WatchdogService {
    constructor(page, config = {}) {
        this.page = page;

        this.intervalId = null;
        this.lastActivity = Date.now();
        this.noItemSince = null;

        this.rateMonitor = config.rateMonitor;
        this.rateMonitorStarted = false;
        this.monitorStarting = false;

        this.healthCheckRunning = false;

        this.config = {
            activityTimeout: config.timeout || 600000,
            interval: config.interval || 5000,
            usePm2: config.usePm2 !== undefined ? config.usePm2 : true,
            noItemTimeout: config.noItemTimeout || 3600000,
        };
    }

    async isPageReady() {
        const url = this.page.url();
        if (url.includes("login")) return false;

        const loginInput = await this.page.$('input[name="username"]');
        if (loginInput) return false;

        const itemList = await this.page.$(".content-body");

        return !!itemList;
    }

    reset() {
        this.lastActivity = Date.now();
    }

    async tryStartMonitoring() {

        if (this.rateMonitorStarted || this.monitorStarting) {
            return false;
        }

        const hasCards = await this.page.$('.content-body');
        if (!hasCards) {
            return false;
        }

        try {
            this.monitorStarting = true;

            logger.log("✅ Cards detected, starting rate monitor...");

            await this.setupMonitoring();

            this.rateMonitorStarted = true;
            this.lastActivity = Date.now();

            return true;

        } finally {
            this.monitorStarting = false;
        }
    }

    async checkHealth() {

        if (this.healthCheckRunning) {
            return;
        }

        this.healthCheckRunning = true;

        try {

            if (this.page.isClosed()) {
                logger.log("❌ Page is closed");
                await this.handleFailure();
                return;
            }

            if (!await this.isPageReady()) {
                logger.log("❌ Page is not ready");
                await this.handleFailure();
                return;
            }

            if (await this.tryStartMonitoring()) {
                return;
            }

            if (this.rateMonitorStarted) {

                const inactiveTime = Date.now() - this.lastActivity;

                if (inactiveTime > this.config.activityTimeout) {
                    logger.log(
                        `❌ No activity for ${Math.floor(inactiveTime / 1000)}s (threshold: ${this.config.activityTimeout / 1000}s)`
                    );

                    await this.handleFailure();
                }
            }

        } catch (err) {

            logger.log(`❌ Health check error: ${err.message}`);
            await this.handleFailure();

        } finally {

            this.healthCheckRunning = false;

        }
    }

    async setupMonitoring() {
        try {

            const hasHook = await this.page.evaluate(() => typeof window.onCardsChangedHook === "function");

            if (!hasHook) {
                await this.page.exposeFunction("onCardsChangedHook", () => this.reset());
            }

            if (this.rateMonitor) {
                await this.rateMonitor.start(this.page);
            }

            logger.log("✅ Rate monitoring initialized");

        } catch (err) {

            logger.log(`❌ Failed to setup monitoring: ${err.message}`);
            throw err;
        }
    }

    async handleFailure() {

        logger.log("🔄 Attempting recovery...");

        try {

            await ensureLoggedIn(this.page);

            logger.log("✅ Recovery successful");

            this.lastActivity = Date.now();
            this.noItemSince = null;

            return;

        } catch (err) {

            logger.log(`❌ Recovery failed: ${err.message}`);
        }

        logger.log("🔄 Restarting application...");
        this.terminate();
    }

    terminate() {

        this.stop();

        if (this.config.usePm2) {
            process.exit(1);
        } else {
            setTimeout(() => process.exit(0), 1000);
        }
    }

    start() {
        this.lastActivity = Date.now();
        void this.runLoop();
        logger.log(`🐕 Watchdog started (interval: ${this.config.interval}ms)`);
    }

    async runLoop() {
        try {
            await this.checkHealth();
        } catch (err) {
            logger.log(`❌ Critical error in loop: ${err.message}`);
        }

        this.intervalId = setTimeout(
            () => this.runLoop(),
            this.config.interval
        );
    }

    stop() {

        if (this.intervalId) {

            clearInterval(this.intervalId);
            this.intervalId = null;

            logger.log("🐕 Watchdog stopped");
        }
    }
}

module.exports = WatchdogService;
