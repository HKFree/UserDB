import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { chromium } from 'playwright';
import { PNG } from 'pngjs';
import { capture } from '../capture.mjs';
import { temporary, png } from './helpers.mjs';

for (const failure of ['Tracy check', 'fallback page']) {
    test(`${failure} failure keeps an image and continues to the next page`, async t => {
        const out = await temporary(t);
        let contexts = 0;
        t.mock.method(chromium, 'launch', async () => ({
            async newContext() {
                const broken = contexts++ === 0;
                return {
                    async route() {},
                    async close() {},
                    async newPage() {
                        return {
                            on() {},
                            async goto() { return { status: () => 200 }; },
                            async evaluate() {},
                            url() { return 'http://127.0.0.1:4500/'; },
                            locator() {
                                return { async count() {
                                    if (broken && failure === 'Tracy check') throw new Error('Closed page');
                                    return 0;
                                } };
                            },
                            async setContent() { throw new Error('Closed fallback page'); },
                            async screenshot() {
                                if (broken && failure === 'fallback page') throw new Error('Screenshot failed');
                                return png();
                            },
                        };
                    },
                };
            },
            async close() {},
        }));
        const records = await capture({
            origin: 'http://127.0.0.1:4500', out, label: 'head',
            pages: [{ id: 'broken', path: '/broken' }, { id: 'next', path: '/next' }],
        });
        assert.equal(records.length, 4);
        assert.equal(records[0].failed, true);
        assert.equal(records[2].failed, false);
        for (const record of records) {
            const image = PNG.sync.read(await readFile(join(out, record.file)));
            assert.equal(image.width, record.width);
            assert.equal(image.height, record.height);
        }
    });
}
