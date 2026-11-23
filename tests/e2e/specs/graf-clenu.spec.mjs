import { test, expect } from '../fixtures.mjs';

const phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

for (const width of [1280, 375]) {
    test.describe(`${width} px`, () => {
        test.use({
            viewport: { width, height: 900 },
            ...(width === 375 ? { userAgent: phone, isMobile: true, hasTouch: true } : {}),
        });

        test('grafy aktivních účastníků se vejdou do okna a ukážou hodnoty měsíce', async ({ page }) => {
            await page.goto('/sprava-grafu/usersgraph?obdobi=vse');
            const grafy = page.locator('.graf-clenu[data-serie]');
            await expect(grafy).toHaveCount(2);
            await expect(grafy.nth(0).locator('path.area')).toHaveCount(3);
            await expect(grafy.nth(1).locator('path.area')).toHaveCount(2);
            await expect(grafy.nth(0).locator('.graf-clenu-popisek-x').first()).toBeVisible();
            // Before the first bubble the month line is hidden; it would stand at the left edge of the graph.
            await expect(grafy.nth(1).locator('.detail')).toBeHidden();

            for (const graf of [grafy.nth(0), grafy.nth(1)]) {
                const okraj = await graf.locator('.graf-clenu-plocha svg').boundingBox();
                expect(okraj.x, 'graf začíná v okně').toBeGreaterThanOrEqual(0);
                expect(okraj.x + okraj.width, 'graf končí v okně').toBeLessThanOrEqual(width);
            }
            expect(await page.evaluate(() => document.documentElement.scrollWidth), 'stránka se neposouvá do strany').toBeLessThanOrEqual(width);

            const plocha = grafy.nth(0).locator('.graf-clenu-plocha');
            const velikost = await plocha.locator('svg').boundingBox();
            const stred = { x: velikost.width / 2, y: velikost.height - 5 };
            if (width === 375) await plocha.locator('svg').tap({ position: stred });
            else await plocha.locator('svg').hover({ position: stred });
            const bublina = plocha.locator('.graf-clenu-bublina');
            await expect(bublina).toBeVisible();
            for (const popisek of ['Automatem', 'Ručně', 'Částečně', 'Celkem']) await expect(bublina).toContainText(popisek);
            await expect(bublina.locator('.graf-clenu-bublina-cislo')).toHaveCount(4);
            const okrajBubliny = await bublina.boundingBox();
            expect(okrajBubliny.x + okrajBubliny.width, 'bublina končí v okně').toBeLessThanOrEqual(width);
        });

        test('klepnutí na pravý okraj grafu ukáže poslední měsíc', async ({ page }) => {
            // Twelve months: the right padding lies within half a month after the last point, where Rickshaw
            // would pick the month before it.
            await page.goto('/sprava-grafu/usersgraph?obdobi=12m');
            const graf = page.locator('.graf-clenu[data-serie]').first();
            const okraj = await graf.boundingBox();
            // Right padding of the graph, beyond its last point.
            const vpravo = { x: okraj.width - 4, y: okraj.height / 2 };
            if (width === 375) await graf.tap({ position: vpravo });
            else await graf.click({ position: vpravo });
            const posledni = await page.locator('.graf-clenu-tabulka tbody tr').first().locator('td').first().textContent();
            await expect(graf.locator('.graf-clenu-bublina-mesic')).toHaveText(posledni);
        });
    });
}

test('přepínač období zkrátí grafy i tabulku na posledních 12 měsíců', async ({ page }) => {
    await page.goto('/sprava-grafu/usersgraph');
    await page.getByRole('link', { name: '12 měsíců', exact: true }).click();
    await expect(page).toHaveURL(/obdobi=12m/);
    await expect(page.getByRole('link', { name: '12 měsíců', exact: true })).toHaveAttribute('aria-current', 'page');
    const tabulka = page.locator('.graf-clenu-tabulka');
    await expect(tabulka).toBeHidden();
    await page.getByRole('button', { name: 'Hodnoty v tabulce' }).click();
    await expect(tabulka).toBeVisible();
    // Twelve months back up to the current one.
    await expect(tabulka.locator('tbody tr')).toHaveCount(13);
});
