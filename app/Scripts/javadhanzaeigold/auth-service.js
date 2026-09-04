const { clickIfExists } = require("./utils/puppeteer-utils");

async function login(page, { username, password, loginUrl }) {
    await page.goto(loginUrl, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
    });

    await clickIfExists(page, ".swal2-cancel", 15000);

    await page.waitForSelector("#form", {
        visible: true,
        timeout: 15000,
    });

    await page.waitForSelector('input[name="username"]', {
        visible: true,
        timeout: 15000,
    });
    await page.waitForSelector('input[name="password"]', {
        visible: true,
        timeout: 15000,
    });
    await page.waitForSelector('input[type="checkbox"]', {
        visible: true,
        timeout: 15000,
    });

    await page.type('input[name="username"]', username);
    await page.type('input[name="password"]', password);
    await clickIfExists(page, 'input[type="checkbox"]', 15000);
    await page.click('button[type="submit"]');

    try {
        await page.waitForFunction(() => !window.location.href.includes("login"), {
            timeout: 20000
        });

        await page.waitForSelector(".content-body", {
            visible: true,
            timeout: 10000
        });

        console.log("✅ Successfully navigated away from login page.");
    } catch (error) {
        throw new Error("❌ Login failed: Still stuck on login page after timeout.");
    }
}

async function ensureLoggedIn(page) {
    const username = process.env.PUPPETEER_USER;
    const password = process.env.PUPPETEER_PASS;
    const loginUrl = process.env.PUPPETEER_URL;

    try {
        const currentUrl = page.url();
        const isLoginPage = currentUrl.includes("login");

        if (isLoginPage) {
            await login(page, { username, password, loginUrl });
            return;
        }

        // اگر نیاز به بررسی session/element خاص دارید
        const hasItemPricesList = await page.$(".content-body");
        if (!hasItemPricesList) {
            await login(page, { username, password, loginUrl });
        }
    } catch (err) {
        console.warn("Login/session check failed, retrying login...", err.message);
        await login(page, { username, password, loginUrl });
    }
}

module.exports = {
    login,
    ensureLoggedIn,
};
