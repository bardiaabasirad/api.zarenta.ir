const crypto = require("crypto");

function generateRequestHash(formattedData, priceSourceId) {
    const payload = JSON.stringify({
        items: formattedData,
        price_source_id: priceSourceId
    });
    return crypto.createHash('sha256').update(payload).digest('hex');
}

module.exports = {
    generateRequestHash
};
