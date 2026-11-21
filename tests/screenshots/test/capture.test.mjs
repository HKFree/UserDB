import test, { after, before, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { once } from 'node:events';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { PNG } from 'pngjs';
import { capture } from '../capture.mjs';

describe('capture fixtures', () => {
    let out, head, foreignOrigin, foreign, server;
    const foreignRequests = [];
    after(async () => {
        for (const fixture of [server, foreign].filter(Boolean)) {
            fixture.closeAllConnections();
            fixture.close();
        }
        if (out) await rm(out, { recursive: true, force: true });
    });
    before(async () => {
        out = await mkdtemp(join(tmpdir(), 'userdb-screenshots-test-'));
        foreign = createServer((request, response) => {
            foreignRequests.push(request.url);
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            response.end('<html><style>html { background: #ff00ff; }</style><h1>Foreign page</h1></html>');
        });
        foreign.listen(0, '127.0.0.1');
        await once(foreign, 'listening');
        foreignOrigin = `http://127.0.0.1:${foreign.address().port}`;
        server = createServer((request, response) => {
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            const redirects = {
                '/redirect': `${foreignOrigin}/redirected`,
                '/redirect-name': `http://localhost:${foreign.address().port}/by-name`,
                '/redirect-external': 'http://example.com/',
            };
            if (redirects[request.url]) {
                response.writeHead(302, { Location: redirects[request.url] });
                response.end();
                return;
            }
            if (request.url === '/missing') response.statusCode = 404;
            response.end(`<html><meta name="viewport" content="width=device-width"><style>
                body { margin: 0; }
                #moving { width: 40px; height: 40px; background: blue; animation: move 1s infinite; }
                @keyframes move { from { transform: translateX(0); } to { transform: translateX(100px); } }
                </style><div id="moving"></div><div id="tracy-debug">${Math.random()}</div>
                ${request.url === '/tracy' ? '<div id="tracy-bs">Failure</div>' : ''}
                ${request.url === '/tall' ? '<div style="height:9000px;background:lime"></div>' : ''}
                ${request.url === '/foreign' ? `<img src="http://127.0.0.1:${foreign.address().port}/image">` : ''}
                </html>`);
        });
        server.listen(0, '127.0.0.1');
        await once(server, 'listening');
        const pages = [
            'stable', 'missing', 'tracy', 'tall', 'foreign', 'redirect', 'redirect-name', 'redirect-external',
        ].map(id => ({ id, path: `/${id}` }));
        pages.push({ id: 'stable-again', path: '/stable' });
        const origin = `http://127.0.0.1:${server.address().port}`;
        head = await capture({ origin, pages, label: 'head', out });
    });

    const record = (id, viewport) => head.find(item => item.id === id && item.viewport === viewport);
    for (const viewport of ['desktop', 'mobile']) {
        test(`${viewport}: animation and random Tracy toolbar do not change the image`, async () => {
            assert.deepEqual(await readFile(join(out, `head/stable-${viewport}.png`)),
                await readFile(join(out, `head/stable-again-${viewport}.png`)));
        });
        test(`${viewport}: records HTTP 404`, () => {
            assert.equal(record('missing', viewport).status, 404);
        });
        test(`${viewport}: records Tracy bluescreens`, () => {
            assert.equal(record('tracy', viewport).tracy, true);
        });
        test(`${viewport}: crops tall pages to 8000 pixels`, async () => {
            const tall = record('tall', viewport);
            assert.equal(tall.truncated, true);
            assert.equal(tall.height, 8000);
            assert.equal(PNG.sync.read(await readFile(join(out, tall.file))).height, 8000);
        });
        test(`${viewport}: blocks directly requested foreign assets`, () => {
            assert.equal(record('foreign', viewport).blockedRequests, 1);
            assert.equal(foreignRequests.includes('/image'), false);
        });
        test(`${viewport}: foreign redirect is failed and stores a fallback instead of the foreign page`, async () => {
            const redirected = record('redirect', viewport);
            assert.equal(redirected.failed, true);
            assert.equal(redirected.status, 200);
            assert.equal(redirected.finalUrl, `${foreignOrigin}/redirected`);
            const image = PNG.sync.read(await readFile(join(out, redirected.file)));
            assert.notDeepEqual([...image.data.subarray(0, 4)], [255, 0, 255, 255]);
            assert.deepEqual(await readFile(join(out, redirected.file)),
                await readFile(join(out, record('redirect-name', viewport).file)));
        });
        for (const id of ['redirect-name', 'redirect-external']) {
            test(`${viewport}: cannot follow ${id} through DNS`, () => {
                assert.equal(record(id, viewport).failed, true);
                assert.equal(record(id, viewport).status, 0);
                assert.equal(foreignRequests.includes('/by-name'), false);
            });
        }
    }
    test('all captured images respect the artifact dimensions', () => {
        assert.ok(head.every(item => item.width <= 1440 && item.height <= 8000));
    });
});
