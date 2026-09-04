async function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function clickIfExists(page, selector, timeout = 3000) {
    try {
        await page.waitForSelector(selector, {
            visible: true,
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
