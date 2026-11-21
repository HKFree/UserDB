import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdir, writeFile, access } from 'node:fs/promises';
import { join } from 'node:path';
import { compareImages, compare } from '../compare.mjs';
import { temporary, png, details, headSha, baseSha } from './helpers.mjs';

test('identical images are unchanged', () => {
    const result = compareImages(png(), png());
    assert.equal(result.state, 'unchanged');
    assert.equal(result.diffPixels, 0);
    assert.equal(result.buffer, null);
});

test('one different pixel produces a diff of one pixel', () => {
    const result = compareImages(png(), png(10, 10, true));
    assert.equal(result.state, 'changed');
    assert.equal(result.diffPixels, 1);
    assert.equal(result.diffRatio, 0.01);
    assert.ok(result.buffer);
});

test('height changes count even when padded pixels match', () => {
    assert.equal(compareImages(png(), png(10, 11), 100).state, 'changed');
});

test('maxDiffPixels permits the specified difference', () => {
    const result = compareImages(png(), png(10, 10, true), 1);
    assert.equal(result.state, 'unchanged');
    assert.equal(result.diffPixels, 1);
    assert.equal(result.buffer, null);
});

test('an unavailable base preserves head previews and the enumerated reason', async t => {
    const out = await temporary(t);
    await mkdir(join(out, 'head'));
    await writeFile(join(out, 'head/capture.json'), JSON.stringify([
        { id: 'homepage', path: '/', viewport: 'desktop', maxDiffPixels: 0, ...details('head') },
    ]));
    const result = await compare({ out, headSha, baseSha, baseReason: 'no-flox-setup' });
    assert.deepEqual(result.base, { sha: baseSha, available: false, reason: 'no-flox-setup' });
    assert.equal(result.snapshots[0].state, 'unchanged');
    assert.equal(result.snapshots[0].base, null);
    assert.equal(result.snapshots[0].diff, null);
});

test('console errors are recorded without turning a healthy page into an error', async t => {
    const out = await temporary(t);
    for (const label of ['head', 'base']) {
        await mkdir(join(out, label));
        await writeFile(join(out, label, 'homepage-desktop.png'), png());
        await writeFile(join(out, label, 'capture.json'), JSON.stringify([
            { id: 'homepage', path: '/', viewport: 'desktop', maxDiffPixels: 0, ...details(label), consoleErrors: 3 },
        ]));
    }
    const result = await compare({ out, headSha, baseSha });
    assert.equal(result.snapshots[0].state, 'unchanged');
    assert.equal(result.snapshots[0].head.consoleErrors, 3);
});

for (const [name, headChanges, baseChanges, expected] of [
    ['base 404', {}, { status: 404 }, 'new'],
    ['head 404', { status: 404 }, {}, 'error'],
    ['head Tracy', { tracy: true }, {}, 'error'],
    ['failed navigation', { failed: true }, {}, 'error'],
    ['base 500', {}, { status: 500 }, 'base-error'],
    ['both failed', { status: 500 }, { status: 500 }, 'error'],
]) {
    test(name, async t => {
        const out = await temporary(t);
        for (const [label, changes] of [['head', headChanges], ['base', baseChanges]]) {
            await mkdir(join(out, label));
            await writeFile(join(out, label, 'capture.json'), JSON.stringify([
                { id: 'homepage', path: '/', viewport: 'desktop', maxDiffPixels: 0, ...details(label), ...changes },
            ]));
        }
        const result = await compare({ out, headSha, baseSha });
        assert.equal(result.snapshots[0].state, expected);
        assert.equal(result.snapshots[0].diff, null);
        await assert.rejects(access(join(out, 'diff')));
    });
}
