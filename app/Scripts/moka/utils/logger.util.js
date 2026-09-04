const fs = require("fs");
const path = require("path");
const { DateTime } = require("luxon");

class Logger {
    constructor() {
        this.logFilePath = path.join(__dirname, "../../errors.log");
        this.errorsDir = path.join(__dirname, "../../errors");
    }

    getTehranTime(format = "yyyy-MM-dd HH:mm:ss") {
        return DateTime.now().setZone("Asia/Tehran").toFormat(format);
    }

    log(message) {
        const time = this.getTehranTime();
        const logMessage = `[${time}] ${message}\n`;
        console.log(logMessage.trim());
        fs.appendFileSync(this.logFilePath, logMessage);
    }

    async saveHtmlDump(page) {
        try {
            if (!fs.existsSync(this.errorsDir)) {
                fs.mkdirSync(this.errorsDir, { recursive: true });
            }

            const html = await page.content();
            const timestamp = this.getTehranTime("yyyy-MM-dd_HH-mm-ss");
            const dumpPath = path.join(this.errorsDir, `stuck_${timestamp}.html`);

            fs.writeFileSync(dumpPath, html);
            this.log(`💾 HTML dump saved: ${dumpPath}`);
        } catch (err) {
            this.log(`❌ Failed to save HTML dump: ${err.message}`);
        }
    }
}

module.exports = new Logger();
