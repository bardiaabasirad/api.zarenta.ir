const fs = require("fs");
const path = require("path");

const DEBUG_LOG_PATH = path.join(__dirname, "../../debug-29ecae.log");
const INGEST_URL =
    "http://127.0.0.1:7367/ingest/be3b61c0-d8db-4461-8637-906a4b3bad9a";
const SESSION_ID = "29ecae";

/**
 * Debug-mode NDJSON logger (file + optional local ingest).
 * Safe on remote Ubuntu: file write always; fetch fails silently if ingest unavailable.
 */
function agentLog(location, message, data = {}, hypothesisId = null) {
    const payload = {
        sessionId: SESSION_ID,
        location,
        message,
        data,
        timestamp: Date.now(),
        hypothesisId,
        pid: process.pid,
    };

    try {
        fs.appendFileSync(DEBUG_LOG_PATH, `${JSON.stringify(payload)}\n`);
    } catch (_) {
        /* ignore disk errors during debug */
    }

    fetch(INGEST_URL, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-Debug-Session-Id": SESSION_ID,
        },
        body: JSON.stringify(payload),
    }).catch(() => {});
}

module.exports = { agentLog, DEBUG_LOG_PATH };
