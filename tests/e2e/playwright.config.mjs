import { fileURLToPath } from 'node:url';
import { defineConfig } from '@playwright/test';

const origin = process.env.USERDB_E2E_ORIGIN;
if (!origin) {
    throw new Error('Run scripts/userdb_e2e.sh: it rebuilds the database of the browser tests and starts their web.');
}
const out = fileURLToPath(new URL('../../temp/e2e/', import.meta.url));

export default defineConfig({
    testDir: './specs',
    testMatch: '**/*.spec.mjs',
    outputDir: `${out}results`,
    // The tests share one database and one single-process PHP server.
    fullyParallel: false,
    workers: 1,
    // A test that passes only when repeated hides a defect of the test or of the application.
    retries: 0,
    forbidOnly: Boolean(process.env.CI),
    timeout: 30_000,
    // Twice the default: one slow answer of the single-process PHP server must not fail a correct test.
    expect: { timeout: 10_000 },
    reporter: [
        [process.env.CI ? 'github' : 'list'],
        ['html', { outputFolder: `${out}report`, open: 'never' }],
    ],
    use: {
        baseURL: origin,
        browserName: 'chromium',
        viewport: { width: 1280, height: 900 },
        locale: 'cs-CZ',
        timezoneId: 'Europe/Prague',
        serviceWorkers: 'block',
        actionTimeout: 10_000,
        navigationTimeout: 15_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions: {
            // Playwright does not route redirect hops. Without name resolution a redirect cannot reach a named host.
            args: ['--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1'],
        },
    },
});
