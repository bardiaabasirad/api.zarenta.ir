const logger = require("../utils/logger.util");
const { ensureLoggedIn } = require("../auth-service");

class WatchdogService {
    constructor(page, config = {}) {
        this.page = page;

        this.intervalId = null;
        this.lastActivity = Date.now();
        this.noItemSince = null;

        this.rateMonitor = config.rateMonitor || null;
        this.rateMonitorStarted = false;

        this.healthCheckRunning = false;
        this.isRecovering = false;
        this.marketUnavailableLogged = false;

        this.lastDatetime = null;
        this.datetimeFrozenSince = null;

        this.config = {
            activityTimeout: config.timeout || 600000,
            interval: config.interval || 5000,
            usePm2: config.usePm2 !== undefined ? config.usePm2 : true,
            noItemTimeout: config.noItemTimeout || 3600000,
            freezeTimeout: config.freezeTimeout || 15000,
            pageReadyTimeout: config.pageReadyTimeout || 30000,
            pageReadyCheckInterval: config.pageReadyCheckInterval || 1000,
        };

        this.registerRateMonitorActivityHook();
    }

    /**
     * اتصال RateMonitor به Watchdog.
     *
     * RateMonitor هنگام دریافت event واقعی از observer یا initial scrape،
     * این hook را اجرا می‌کند و lastActivity به‌روزرسانی می‌شود.
     */
    registerRateMonitorActivityHook() {
        if (
            !this.rateMonitor ||
            typeof this.rateMonitor.markActivity !== "function"
        ) {
            return;
        }

        this.rateMonitor.markActivity(() => this.reset());
    }

    /**
     * بررسی می‌کند که صفحه از login خارج شده و shell اصلی اپلیکیشن
     * قابل دسترس است.
     *
     * نکته:
     * در زمان بسته‌بودن بازار ممکن است #ItemPricesList و حتی
     * .noitem-container از DOM حذف شوند؛ پس نبودن آن‌ها به‌تنهایی
     * به معنای خراب‌بودن یا آماده‌نبودن صفحه نیست.
     */
    async isPageReady() {
        if (!this.page || this.page.isClosed()) {
            return false;
        }

        try {
            const url = this.page.url();

            if (url.includes("#auth/login")) {
                return false;
            }

            const loginInput = await this.page.$('input[name="UserName"]');

            if (loginInput) {
                return false;
            }

            /*
             * top-nav-datetime یک selector پایدار در صفحه‌ی اصلی است که
             * حتی در صورت حذف کامل بخش نرخ‌ها نیز باید وجود داشته باشد.
             */
            const appShell = await this.page.$(".top-nav-datetime");

            if (appShell) {
                return true;
            }

            /*
             * fallback برای زمانی که selector layout تغییر کند ولی بخش نرخ
             * یا پیام no-item هنوز در DOM باشند.
             */
            const itemList = await this.page.$("#ItemPricesList");
            const noItemContainer = await this.page.$(".noitem-container");

            return Boolean(itemList || noItemContainer);
        } catch (err) {
            logger.log(`⚠️ Could not verify page readiness: ${err.message}`);
            return false;
        }
    }

    /**
     * منتظر آماده‌شدن صفحه‌ی اصلی نرخ‌ها می‌ماند.
     *
     * صرف خارج‌شدن از login برای موفق‌بودن recovery کافی نیست.
     */
    async waitForPageReady() {
        const startedAt = Date.now();

        while (Date.now() - startedAt < this.config.pageReadyTimeout) {
            if (await this.isPageReady()) {
                return true;
            }

            await new Promise((resolve) => {
                setTimeout(resolve, this.config.pageReadyCheckInterval);
            });
        }

        throw new Error(
            `Rates page did not become ready within ${this.config.pageReadyTimeout}ms`
        );
    }

    /**
     * توسط RateMonitor، هنگام رسیدن یک event معتبر observer یا initial scrape
     * فراخوانی می‌شود.
     */
    reset() {
        this.lastActivity = Date.now();
    }

    /**
     * راه‌اندازی کامل مانیتور نرخ‌ها.
     *
     * attachObserver به‌تنهایی کافی نیست؛ زیرا reload mapping، polling،
     * initial scrape و lifecycle observer در rateMonitor.start() مدیریت می‌شود.
     *
     * @returns {Promise<boolean>}
     * true  => observer با موفقیت روی لیست نرخ‌ها attach شده است.
     * false => صفحه سالم است ولی بخش نرخ‌ها فعلاً وجود ندارد (مثلاً بازار بسته است).
     */
    async setupMonitoring() {
        if (!this.rateMonitor) {
            throw new Error("Rate monitor instance is not configured");
        }

        try {
            this.registerRateMonitorActivityHook();

            const observerAttached = await this.rateMonitor.start(this.page);

            this.rateMonitorStarted = Boolean(observerAttached);

            if (observerAttached) {
                this.lastActivity = Date.now();
                this.marketUnavailableLogged = false;

                logger.log("✅ Rate monitoring initialized");
            } else {
                logger.log(
                    "ℹ️ Rate monitor started, but rate list is currently unavailable"
                );
            }

            return observerAttached;
        } catch (err) {
            this.rateMonitorStarted = false;

            logger.log(`❌ Failed to setup monitoring: ${err.message}`);
            throw err;
        }
    }

    /**
     * توقف امن مانیتور قبلی قبل از recovery یا بازگشت بازار.
     *
     * این کار مانع باقی‌ماندن MutationObserver قبلی و timerهای mapping
     * پس از navigation، حذف DOM یا restart می‌شود.
     */
    async stopMonitoringSafe() {
        try {
            if (
                this.rateMonitor &&
                typeof this.rateMonitor.stop === "function"
            ) {
                await this.rateMonitor.stop(this.page);
                logger.log("🛑 Rate monitor stopped");
            }
        } catch (err) {
            /*
             * stop نباید recovery را متوقف کند؛ ممکن است page بین راه navigate
             * شده باشد یا observer قبلی پیش‌تر disconnect شده باشد.
             */
            logger.log(
                `⚠️ Could not stop rate monitor cleanly: ${err.message}`
            );
        } finally {
            this.rateMonitorStarted = false;
        }
    }

    /**
     * در صورت وجود لیست نرخ‌ها، RateMonitor را فعال می‌کند.
     */
    async tryStartMonitoring() {
        if (this.rateMonitorStarted || this.isRecovering) {
            return false;
        }

        try {
            const itemList = await this.page.$("#ItemPricesList");

            if (!itemList) {
                return false;
            }

            logger.log("✅ Rate list detected, starting rate monitor...");

            const observerAttached = await this.setupMonitoring();

            return observerAttached;
        } catch (err) {
            logger.log(`❌ Failed to start monitoring: ${err.message}`);
            this.rateMonitorStarted = false;

            return false;
        }
    }

    /**
     * بررسی سلامت صفحه، وضعیت بازار، فعالیت observer و عدم‌فریز بودن صفحه.
     */
    async checkHealth() {
        if (this.healthCheckRunning || this.isRecovering) {
            return;
        }

        this.healthCheckRunning = true;

        try {
            if (!this.page || this.page.isClosed()) {
                logger.log("❌ Page is closed");
                await this.handleFailure("Page is closed");
                return;
            }

            if (!await this.isPageReady()) {
                logger.log("❌ Page is not ready");
                await this.handleFailure("Page is not ready");
                return;
            }

            if (await this.checkPageFreeze()) {
                logger.log("🔄 Page freeze detected, terminating immediately...");
                this.terminate();
                return;
            }

            const itemList = await this.page.$("#ItemPricesList");
            const noItemContainer = await this.page.$(".noitem-container");

            /*
             * وضعیت اول: بازار یا نرخ‌ها موقتاً در دسترس نیستند.
             *
             * مطابق توضیح شما، ممکن است در پایان بازار کل HTML نرخ‌ها حذف شود.
             * این حالت failure نیست و نباید هر 5 ثانیه recovery اجرا شود.
             */
            if (!itemList && !noItemContainer) {
                if (!this.marketUnavailableLogged) {
                    logger.log(
                        "ℹ️ Rate section is not available; market may be closed. Waiting for it to return..."
                    );

                    this.marketUnavailableLogged = true;
                }

                this.noItemSince = null;

                /*
                 * اگر observer روی DOM قدیمی نصب بوده، آن را متوقف می‌کنیم.
                 * وقتی #ItemPricesList مجدداً وارد DOM شود، tryStartMonitoring
                 * monitor را روی container جدید راه‌اندازی خواهد کرد.
                 */
                if (this.rateMonitorStarted) {
                    await this.stopMonitoringSafe();
                }

                return;
            }

            this.marketUnavailableLogged = false;

            /*
             * وضعیت دوم: صفحه صریحاً اعلام می‌کند آیتمی وجود ندارد.
             */
            if (noItemContainer) {
                if (!this.noItemSince) {
                    this.noItemSince = Date.now();
                    logger.log("⚠️ No items available, starting timer");
                } else {
                    const duration = Date.now() - this.noItemSince;

                    if (duration > this.config.noItemTimeout) {
                        logger.log(
                            `❌ No items for ${Math.floor(duration / 1000)}s ` +
                            `(threshold: ${this.config.noItemTimeout / 1000}s)`
                        );

                        await this.handleFailure("No items timeout exceeded");
                    }
                }

                /*
                 * اگر قبلاً observer روی لیست قدیمی نصب بوده، بهتر است متوقف شود.
                 * پس از بازگشت #ItemPricesList، monitor از نو attach خواهد شد.
                 */
                if (this.rateMonitorStarted) {
                    await this.stopMonitoringSafe();
                }

                return;
            }

            /*
             * وضعیت سوم: نرخ‌ها بازگشته‌اند.
             */
            if (this.noItemSince) {
                logger.log("✅ Items are back");
                this.noItemSince = null;

                await this.stopMonitoringSafe();

                const observerAttached = await this.setupMonitoring();

                if (observerAttached) {
                    logger.log("🔁 Rate monitor restarted after items returned");
                }

                return;
            }

            if (await this.tryStartMonitoring()) {
                return;
            }

            if (this.rateMonitorStarted) {
                const inactiveTime = Date.now() - this.lastActivity;

                if (inactiveTime > this.config.activityTimeout) {
                    logger.log(
                        `❌ No activity for ${Math.floor(inactiveTime / 1000)}s ` +
                        `(threshold: ${this.config.activityTimeout / 1000}s)`
                    );

                    await this.handleFailure("Activity timeout exceeded");
                }
            }
        } catch (err) {
            logger.log(`❌ Health check error: ${err.message}`);
            await this.handleFailure(`Health check error: ${err.message}`);
        } finally {
            this.healthCheckRunning = false;
        }
    }

    /**
     * تشخیص freeze از روی ساعت/تاریخ بالای صفحه.
     */
    async checkPageFreeze() {
        try {
            const el = await this.page.$(".top-nav-datetime span");

            if (!el) {
                this.lastDatetime = null;
                this.datetimeFrozenSince = null;
                return false;
            }

            const text = await this.page.evaluate(
                (element) => element.textContent.trim(),
                el
            );

            if (text !== this.lastDatetime) {
                this.lastDatetime = text;
                this.datetimeFrozenSince = null;

                return false;
            }

            if (!this.datetimeFrozenSince) {
                this.datetimeFrozenSince = Date.now();
            }

            const frozenDuration = Date.now() - this.datetimeFrozenSince;

            if (frozenDuration > this.config.freezeTimeout) {
                logger.log(
                    `❌ Page frozen for ${Math.floor(frozenDuration / 1000)}s ` +
                    `(datetime unchanged: "${text}")`
                );

                return true;
            }

            return false;
        } catch (err) {
            logger.log(`⚠️ Could not check page datetime: ${err.message}`);
            return false;
        }
    }

    /**
     * Recovery کامل:
     *
     * 1. جلوگیری از اجرای recovery هم‌زمان
     * 2. توقف monitor / observer / timerهای قبلی
     * 3. اطمینان از session و login
     * 4. انتظار برای آماده‌شدن واقعی shell اصلی صفحه
     * 5. اجرای کامل rateMonitor.start()
     *
     * لاگ Recovery successful فقط زمانی ثبت می‌شود که:
     * - صفحه اصلی واقعاً آماده شده باشد؛ و
     * - یا observer با موفقیت attach شده باشد؛
     * - یا بازار موقتاً بسته باشد و این وضعیت صریحاً ثبت شود.
     */
    async handleFailure(reason = "Unknown failure") {
        if (this.isRecovering) {
            logger.log(
                "⏳ Recovery is already in progress, skipping duplicate request"
            );

            return;
        }

        this.isRecovering = true;

        logger.log(`🔄 Attempting recovery... Reason: ${reason}`);

        try {
            if (!this.page || this.page.isClosed()) {
                throw new Error(
                    "Page is closed and cannot be recovered by ensureLoggedIn"
                );
            }

            await this.stopMonitoringSafe();

            await ensureLoggedIn(this.page);
            await this.waitForPageReady();

            const itemList = await this.page.$("#ItemPricesList");
            const noItemContainer = await this.page.$(".noitem-container");

            this.lastDatetime = null;
            this.datetimeFrozenSince = null;

            /*
             * بازار احتمالاً بسته است و بخش نرخ‌ها از HTML حذف شده.
             * این failure نیست؛ منتظر می‌مانیم تا در چرخه‌های health check
             * لیست نرخ‌ها دوباره ظاهر شود.
             */
            if (!itemList && !noItemContainer) {
                this.lastActivity = Date.now();
                this.noItemSince = null;
                this.marketUnavailableLogged = true;

                logger.log(
                    "✅ Recovery successful; page is ready but rate section is currently unavailable"
                );

                return;
            }

            /*
             * صفحه‌ی نرخ آماده است اما آیتمی ندارد.
             */
            if (noItemContainer) {
                this.lastActivity = Date.now();
                this.noItemSince = Date.now();
                this.marketUnavailableLogged = false;

                logger.log(
                    "✅ Recovery successful; rates page is ready but currently has no items"
                );

                return;
            }

            const observerAttached = await this.setupMonitoring();

            if (!observerAttached || !this.rateMonitorStarted) {
                throw new Error(
                    "Rate monitor did not attach successfully after recovery"
                );
            }

            this.lastActivity = Date.now();
            this.noItemSince = null;
            this.marketUnavailableLogged = false;

            logger.log("✅ Recovery successful; rate monitoring restarted");
        } catch (err) {
            logger.log(`❌ Recovery failed: ${err.message}`);
            logger.log("🔄 Restarting application...");

            this.terminate();
        } finally {
            this.isRecovering = false;
        }
    }

    terminate() {
        this.stop();

        if (this.config.usePm2) {
            process.exit(1);
            return;
        }

        setTimeout(() => process.exit(0), 1000);
    }

    start() {
        if (this.intervalId) {
            logger.log("ℹ️ Watchdog is already running");
            return;
        }

        this.lastActivity = Date.now();

        void this.runLoop();

        logger.log(
            `🐕 Watchdog started (interval: ${this.config.interval}ms)`
        );
    }

    async runLoop() {
        try {
            await this.checkHealth();
        } catch (err) {
            logger.log(`❌ Critical error in watchdog loop: ${err.message}`);
        }

        this.intervalId = setTimeout(
            () => this.runLoop(),
            this.config.interval
        );
    }

    stop() {
        if (!this.intervalId) {
            return;
        }

        clearTimeout(this.intervalId);
        this.intervalId = null;

        logger.log("🐕 Watchdog stopped");
    }
}

module.exports = WatchdogService;
