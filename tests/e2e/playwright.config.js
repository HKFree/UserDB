const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: '.',
    workers: 1,
    timeout: 60000,
    reporter: [['list']],
    // Plný Chromium v novém headless režimu (service worker + Notification API)
    use: { channel: 'chromium', trace: 'retain-on-failure' },
});
