import { test, expect, personas } from '../fixtures.mjs';

/** Links of the top bar and of the sidebar heading; the lists of areas are covered by one area each. */
async function menuLinks(page) {
    return page.evaluate(() => {
        const skipped = ['/sprava/logout', '/demo-osoba'];
        const links = [...document.querySelectorAll('.navbar a[href], .sidebar h4 a[href], .sidebar .nav-sidebar > li:first-child > a[href]')]
            .map(link => new URL(link.href))
            .filter(url => url.origin === location.origin && !url.hash && !skipped.includes(url.pathname))
            .map(url => url.pathname + url.search);
        return [...new Set(links)];
    });
}

/**
 * Pages that depend on a service the local web is disconnected from on purpose. They must still
 * render; the listed consequences of the missing service are not failures.
 */
const disconnected = {
    '/sprava/mapa': { status: 200, allow: [/maps\.google\.com/, /google is not defined/] },
    '/vnejsi-scanner/': { status: 503, allow: [/\b503\b.*\/vnejsi-scanner\//] },
};

for (const [role, persona] of Object.entries({ VV: personas.vv, SO: personas.so, ZSO: personas.zso, 'Družstevník': personas.druzstevnik })) {
    test.describe(`menu jako ${role}`, () => {
        test.use({ persona });

        test('každá stránka z menu se načte bez chyby', async ({ page, problems }) => {
            Object.values(disconnected).flatMap(expected => expected.allow).forEach(pattern => problems.allow(pattern));
            await page.goto('/');
            await expect(page.locator('.navbar-right').first()).toContainText(`(${persona})`);
            const links = await menuLinks(page);
            expect(links.length).toBeGreaterThan(10);
            for (const link of links) {
                await test.step(link, async () => {
                    const response = await page.goto(link);
                    expect(response.status(), `stav odpovědi ${link}`).toBe(disconnected[link]?.status ?? 200);
                    await expect(page.locator('#tracy-bs')).toHaveCount(0);
                    await expect(page.locator('.navbar-brand')).toBeVisible();
                });
            }
        });
    });
}
