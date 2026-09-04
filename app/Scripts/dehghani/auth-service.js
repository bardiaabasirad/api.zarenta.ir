const { clickIfExists } = require("./utils/puppeteer-utils");

async function login(page, { username, password, loginUrl }) {
    await page.goto(loginUrl, { waitUntil: "networkidle2" });

    await clickIfExists(page, "#close_button", 15000);

    await page.waitForSelector(".loginType-container", {
        visible: true,
        timeout: 15000,
    });
    await clickIfExists(page, ".loginType-container .btn", 15000);

    await page.waitForSelector('input[name="UserName"]', {
        visible: true,
        timeout: 15000,
    });
    await page.waitForSelector('input[name="Password"]', {
        visible: true,
        timeout: 15000,
    });

    await page.type('input[name="UserName"]', username);
    await page.type('input[name="Password"]', password);
    await page.click('button[type="submit"]');

    try {
        await page.waitForFunction(() => !window.location.href.includes("auth/login"), {
            timeout: 20000
        });

        await page.waitForSelector("#ItemPricesList", {
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
        const isLoginPage = currentUrl.includes("#auth/login");

        if (isLoginPage) {
            await login(page, { username, password, loginUrl });
            return;
        }

        // اگر نیاز به بررسی session/element خاص دارید
        const hasItemPricesList = await page.$("#ItemPricesList");
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
