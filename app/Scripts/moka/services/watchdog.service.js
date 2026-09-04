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

        this.healthCheckRunning = false;

        this.lastDatetime = null;
        this.datetimeFrozenSince = null;

        this.config = {
            activityTimeout: config.timeout || 600000,
            interval: config.interval || 5000,
            usePm2: config.usePm2 !== undefined ? config.usePm2 : true,
            noItemTimeout: config.noItemTimeout || 3600000,
            freezeTimeout: config.freezeTimeout || 15000,
        };
    }

    async isPageReady() {
        // Get the current page URL
        const url = this.page.url();

        // If we're on the login route (hash-based), the page is not ready for scraping/actions
        if (url.includes("#auth/login")) return false;

        // If the login username input exists, it likely means we are on (or redirected to) the login form
        const loginInput = await this.page.$('input[name="UserName"]');
        if (loginInput) return false;

        // Look for the main items list container (successful content state)
        const itemList = await this.page.$("#ItemPricesList");

        // Look for the "no items" container (empty state)
        const noItemContainer = await this.page.$(".noitem-container");

        // Consider the page ready if either the items list or the empty-state container is present in the DOM
        return !!(itemList || noItemContainer);
    }

    reset() {
        this.lastActivity = Date.now();
    }

    async tryStartMonitoring() {
        if (this.rateMonitorStarted) {
            return false;
        }

        try {
            const hasCards = await this.page.$('#ItemPricesList .card');
            if (!hasCards) {
                return false;
            }

            logger.log("✅ Cards detected, starting rate monitor...");

            await this.setupMonitoring();

            this.rateMonitorStarted = true;
            this.lastActivity = Date.now();

            return true;

        } catch (err) {
            logger.log(`❌ Failed to start monitoring: ${err.message}`);
            return false;
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

            if (await this.checkPageFreeze()) {
                logger.log("🔄 Page freeze detected, terminating immediately...");
                this.terminate();
                return;
            }

            const noItemContainer = await this.page.$(".noitem-container");

            if (noItemContainer) {

                if (!this.noItemSince) {
                    this.noItemSince = Date.now();
                    logger.log("⚠️ No items available, starting timer");
                } else {

                    const duration = Date.now() - this.noItemSince;

                    if (duration > this.config.noItemTimeout) {
                        logger.log(
                            `❌ No items for ${Math.floor(duration / 1000)}s (threshold: ${this.config.noItemTimeout / 1000}s)`
                        );
                        await this.handleFailure();
                    }
                }

                return;
            } else if (this.noItemSince) {
                logger.log("✅ Items are back");
                this.noItemSince = null;
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

    async checkPageFreeze() {
        try {
            const el = await this.page.$(".top-nav-datetime span");
            if (!el) return false; // تگ وجود نداره، نمی‌تونیم قضاوت کنیم

            const text = await this.page.evaluate(el => el.textContent.trim(), el);

            if (text !== this.lastDatetime) {
                // مقدار تغییر کرده → صفحه زنده‌ست
                this.lastDatetime = text;
                this.datetimeFrozenSince = null;
                return false;
            }

            // مقدار تغییر نکرده
            if (!this.datetimeFrozenSince) {
                this.datetimeFrozenSince = Date.now();
            }

            const frozenDuration = Date.now() - this.datetimeFrozenSince;

            if (frozenDuration > this.config.freezeTimeout) {
                logger.log(
                    `❌ Page frozen for ${Math.floor(frozenDuration / 1000)}s (datetime unchanged: "${text}")`
                );
                return true; // فریز شده
            }

            return false;

        } catch (err) {
            logger.log(`⚠️ Could not check page datetime: ${err.message}`);
            return false; // در صورت خطا، فریز فرض نمی‌کنیم
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
