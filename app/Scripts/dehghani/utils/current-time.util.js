const { DateTime } = require("luxon");

function currentTime(format = "yyyy-MM-dd HH:mm:ss") {
    return DateTime.now().setZone("Asia/Tehran").toFormat(format);
}

module.exports = {
    currentTime
};
