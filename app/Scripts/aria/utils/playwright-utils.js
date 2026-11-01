async function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * اگر selector در timeout ظاهر و visible شد، کلیک می‌کند.
 * @returns {Promise<boolean>}
 */
async function clickIfExists(page, selector, timeout = 3000) {
    try {
        await page.waitForSelector(selector, {
            state: "visible",
            timeout,
        });

        await page.click(selector);
        return true;
    } catch {
        return false;
    }
}

module.exports = {
    wait,
    clickIfExists,
};
