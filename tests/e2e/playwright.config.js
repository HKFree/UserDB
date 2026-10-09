const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: '.',
    workers: 1,
    timeout: 60000,
    // V CI navíc anotace (chyby viditelné i bez přístupu k logu)
    reporter: process.env.CI ? [['list'], ['github']] : [['list']],
    // Plný Chromium v novém headless režimu (service worker + Notification API)
    use: { channel: 'chromium', trace: 'retain-on-failure' },
    // Mock push služby; v Dockeru běží jako služba mockpush (docker-compose.e2e.yml) a použije se ta
    webServer: { command: 'node mock-push.js', url: 'http://127.0.0.1:9999/health', reuseExistingServer: true },
});
