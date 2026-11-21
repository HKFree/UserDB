import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright';
import { PNG } from 'pngjs';
import { idPattern, pathPattern } from './schema.mjs';

const viewports = {
    desktop: { viewport: { width: 1440, height: 900 }, isMobile: false, hasTouch: false },
    mobile: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true },
};

function limitImage(buffer) {
    const source = PNG.sync.read(buffer);
    const width = Math.min(source.width, 1440);
    const height = Math.min(source.height, 8000);
    const image = new PNG({ width, height });
    PNG.bitblt(source, image, 0, 0, width, height, 0, 0);
    return {
        buffer: PNG.sync.write(image), width, height,
        truncated: source.height > height, clippedWidth: source.width > width,
    };
}

function captureRecord(origin, definition, viewport, label) {
    return {
        id: definition.id, path: definition.path, viewport, maxDiffPixels: definition.maxDiffPixels ?? 0,
        file: `${label}/${definition.id}-${viewport}.png`,
        status: 0, finalUrl: new URL(definition.path, origin).href, tracy: false,
        consoleErrors: 0, failed: false, blockedRequests: 0,
    };
}

async function configurePage(context, page, origin, record) {
    page.on('console', message => { if (message.type() === 'error') record.consoleErrors++; });
    page.on('pageerror', () => { record.consoleErrors++; });
    await context.route('**/*', route => {
        if (new URL(route.request().url()).origin === origin) return route.continue();
        record.blockedRequests++;
        return route.abort('blockedbyclient');
    });
}

async function navigate(page, origin, record) {
    try {
        const response = await page.goto(record.finalUrl, { waitUntil: 'networkidle', timeout: 30_000 });
        record.status = response?.status() ?? 0;
    } catch {
        record.failed = true;
    } finally {
        record.finalUrl = page.url();
    }
    // Redirect hops bypass Playwright routing. Never store a screenshot of a foreign origin.
    if (new URL(record.finalUrl).origin !== origin) throw new Error('Navigation left the application origin');
    if (!record.failed) {
        try {
            await page.evaluate(() => document.fonts.ready);
        } catch {
            record.failed = true;
        }
    }
    record.tracy = await page.locator('#tracy-bs').count() > 0;
}

async function pageImage(page, definition) {
    return page.screenshot({
        fullPage: true, animations: 'disabled', caret: 'hide', timeout: 30_000,
        mask: (definition.mask ?? []).map(selector => page.locator(selector)),
        style: '#tracy-debug { display: none !important; }',
    });
}

async function fallbackImage(context, viewport) {
    try {
        const fallback = await context.newPage();
        await fallback.setContent(
            '<html lang="cs"><meta charset="utf-8"><h1>Snímek se nepodařilo pořídit.</h1></html>');
        return await fallback.screenshot({ timeout: 30_000 });
    } catch {
        // A closed browser cannot render the error page. Still keep a PNG for this failed entry.
        const image = new PNG(viewports[viewport].viewport);
        image.data.fill(255);
        return PNG.sync.write(image);
    }
}

async function capturePage(browser, origin, definition, viewport, label, out) {
    const record = captureRecord(origin, definition, viewport, label);
    let context;
    let buffer;
    try {
        context = await browser.newContext({
            ...viewports[viewport], deviceScaleFactor: 1,
            locale: 'cs-CZ', timezoneId: 'Europe/Prague', reducedMotion: 'reduce', colorScheme: 'light',
            serviceWorkers: 'block',
        });
        const page = await context.newPage();
        await configurePage(context, page, origin, record);
        await navigate(page, origin, record);
        buffer = await pageImage(page, definition);
    } catch {
        record.failed = true;
        buffer = await fallbackImage(context, viewport);
    } finally {
        // A page or browser closing unexpectedly must not prevent the remaining entries from being recorded.
        await context?.close().catch(() => { record.failed = true; });
    }
    const { buffer: limited, ...dimensions } = limitImage(buffer);
    Object.assign(record, dimensions);
    await writeFile(resolve(out, record.file), limited);
    return record;
}

export async function capture({ origin, pages, label, out }) {
    if (!['head', 'base'].includes(label)) throw new Error('Invalid capture label');
    origin = new URL(origin).origin;
    await mkdir(resolve(out, label), { recursive: true });
    // Playwright does not route redirect hops. Without name resolution a redirect cannot reach a named host either.
    const browser = await chromium.launch({
        args: ['--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1'],
    });
    const snapshots = [];
    try {
        for (const definition of pages) {
            if (!idPattern.test(definition.id) || !pathPattern.test(definition.path)) {
                throw new Error('Invalid page definition');
            }
            for (const viewport of Object.keys(viewports)) {
                snapshots.push(await capturePage(browser, origin, definition, viewport, label, out));
            }
        }
        await writeFile(resolve(out, label, 'capture.json'), JSON.stringify(snapshots, null, 2) + '\n');
        return snapshots;
    } finally {
        await browser.close();
    }
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    const [origin, label, out] = process.argv.slice(2);
    const pages = JSON.parse(await readFile(new URL('./pages.json', import.meta.url), 'utf8'));
    await capture({ origin, label, out, pages });
}
