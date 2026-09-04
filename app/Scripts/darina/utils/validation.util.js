function isDiffMoreThan(oldVal, newVal, thresholdRatio = 0.5) {
    const oldNum = typeof oldVal === "string" ? parseFloat(oldVal) : oldVal;
    const newNum = typeof newVal === "string" ? parseFloat(newVal) : newVal;

    if (oldNum === null || newNum === null || isNaN(oldNum) || isNaN(newNum)) {
        return false;
    }
    if (oldNum <= 0) return false;

    return Math.abs(newNum - oldNum) / oldNum > thresholdRatio;
}

module.exports = { isDiffMoreThan };
