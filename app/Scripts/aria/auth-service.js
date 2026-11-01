const { clickIfExists, wait } = require("./utils/playwright-utils");
const { agentLog } = require("./utils/debug-agent.util");

function getCredentials() {
    return {
        username: process.env.PLAYWRIGHT_USER || process.env.PUPPETEER_USER,
        password: process.env.PLAYWRIGHT_PASS || process.env.PUPPETEER_PASS,
        loginUrl: process.env.PLAYWRIGHT_URL || process.env.PUPPETEER_URL,
    };
}

function isTransientNavigationError(error) {
    const message = error?.message || String(error || "");
    return /ERR_CONNECTION_CLOSED|ERR_CONNECTION_RESET|ERR_CONNECTION_REFUSED|ERR_EMPTY_RESPONSE|ERR_TIMED_OUT|ERR_NETWORK_CHANGED|ERR_SSL|ERR_CERT|net::ERR_|Timeout|NS_ERROR_NET/i.test(
        message
    );
}

/**
 * navigation مقاوم برای سایت‌هایی که گاهی TLS/اتصال را وسط راه می‌بندند.
 * networkidle برای SPAهایی مثل این معمولاً شکننده است.
 */
async function gotoWithRetry(page, url, { attempts = 4 } = {}) {
    let lastError;

    for (let attempt = 1; attempt <= attempts; attempt += 1) {
        try {
            console.log(
                `🌐 Navigating to login page (attempt ${attempt}/${attempts})...`
            );

            await page.goto(url, {
                waitUntil: "domcontentloaded",
                timeout: 60000,
            });

            /*
             * کمی صبر می‌کنیم تا JS اولیه‌ی SPA (#auth/login) بالا بیاید.
             */
            await wait(1500);
            return;
        } catch (error) {
            lastError = error;

            console.warn(
                `⚠️ Navigation attempt ${attempt} failed: ${error.message}`
            );

            if (!isTransientNavigationError(error) || attempt === attempts) {
                throw error;
            }

            try {
                await page.reload({
                    waitUntil: "domcontentloaded",
                    timeout: 30000,
                });
            } catch {
                /* ignore reload failure before next full goto */
            }

            await wait(1500 * attempt);
        }
    }

    throw lastError;
}

async function login(page, { username, password, loginUrl } = getCredentials()) {
    if (!loginUrl) {
        throw new Error("Login URL is not configured");
    }

    await gotoWithRetry(page, loginUrl);

    await clickIfExists(page, "#close_button", 15000);

    await page.waitForSelector(".loginType-container", {
        state: "visible",
        timeout: 30000,
    });
    await clickIfExists(page, ".loginType-container .btn", 15000);

    await page.waitForSelector('input[name="UserName"]', {
        state: "visible",
        timeout: 15000,
    });
    await page.waitForSelector('input[name="Password"]', {
        state: "visible",
        timeout: 15000,
    });

    await page.fill('input[name="UserName"]', username);
    await page.fill('input[name="Password"]', password);
    await page.click('button[type="submit"]');

    try {
        await page.waitForFunction(
            () => !window.location.href.includes("auth/login"),
            { timeout: 20000 }
        );

        await page.waitForSelector("#ItemPricesList", {
            state: "visible",
            timeout: 15000,
        });

        console.log("✅ Successfully navigated away from login page.");
    } catch (error) {
        throw new Error(
            "❌ Login failed: Still stuck on login page after timeout."
        );
    }
}

async function ensureLoggedIn(page) {
    const { username, password, loginUrl } = getCredentials();

    try {
        const currentUrl = page.url();
        const isLoginPage = currentUrl.includes("#auth/login");

        if (isLoginPage) {
            await login(page, { username, password, loginUrl });
            return;
        }

        const hasItemPricesList = await page.$("#ItemPricesList");
        if (!hasItemPricesList) {
            await login(page, { username, password, loginUrl });
        }
    } catch (err) {
        console.warn(
            "Login/session check failed, retrying login...",
            err.message
        );
        // #region agent log
        agentLog(
            "auth-service.js:ensureLoggedIn:catch",
            "Session check failed — retrying login on possibly dead page",
            {
                err: err.message,
                isDetachedFrame: /detached Frame|Target closed|Execution context was destroyed/i.test(
                    err.message || ""
                ),
                pageClosed: page ? page.isClosed() : null,
            },
            "B"
        );
        // #endregion
        try {
            await login(page, { username, password, loginUrl });
            // #region agent log
            agentLog(
                "auth-service.js:ensureLoggedIn:retryOk",
                "Login retry completed without throw",
                {},
                "B"
            );
            // #endregion
        } catch (loginErr) {
            // #region agent log
            agentLog(
                "auth-service.js:ensureLoggedIn:retryFail",
                "Login retry failed after detached/session error",
                {
                    err: loginErr.message,
                    isDetachedFrame: /detached Frame|Target closed|Execution context was destroyed/i.test(
                        loginErr.message || ""
                    ),
                },
                "B"
            );
            // #endregion
            throw loginErr;
        }
    }
}

module.exports = {
    login,
    ensureLoggedIn,
};
