import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { buildComment, buildFailureComment } from '../comment.mjs';
import { manifest, snapshot, urls, meta, headSha } from './helpers.mjs';

for (const name of ['unchanged', 'changed', 'new', 'error', 'base-error', 'unavailable', 'truncated', 'failure']) {
    test(`comment golden: ${name}`, async () => {
        const state = ['unchanged', 'changed', 'new', 'error', 'base-error'].includes(name) ? name : 'unchanged';
        const data = manifest(state);
        if (name === 'unavailable') {
            data.base.available = false;
            data.base.reason = 'no-flox-setup';
            data.snapshots.forEach(item => { item.base = null; });
        }
        if (name === 'truncated') { data.snapshots[0].head.truncated = true; data.snapshots[0].head.height = 8000; }
        const actual = name === 'failure' ? buildFailureComment(headSha, meta) : buildComment(data, urls, meta);
        const path = new URL(`./fixtures/comment/${name}.md`, import.meta.url);
        if (process.env.UPDATE_SNAPSHOTS === '1') {
            await mkdir(new URL('./fixtures/comment/', import.meta.url), { recursive: true });
            await writeFile(path, actual);
        }
        assert.equal(actual, await readFile(path, 'utf8'));
    });
}

test('maximum number of previews stays below the comment limit', () => {
    const data = manifest();
    data.base.available = false;
    data.base.reason = 'setup-failed';
    data.snapshots = Array.from({ length: 63 }, (_, i) => {
        const item = snapshot('unchanged', `page-${i}`.padEnd(64, 'x'));
        item.path = '/' + 'x'.repeat(127);
        item.base = null;
        return item;
    });
    assert.ok(buildComment(data, urls, meta).length < 65_536);
});

test('maximum changed images stay below the comment limit', () => {
    const data = manifest();
    data.snapshots = Array.from({ length: 21 }, (_, i) => {
        const item = snapshot('changed', `page-${i}`.padEnd(64, 'x'));
        item.path = '/' + 'x'.repeat(127);
        return item;
    });
    assert.ok(buildComment(data, urls, meta).length < 65_536);
});

test('metadata cannot inject markup or links', () => {
    const text = buildComment(manifest(), urls, { ...meta,
        baseBranch: 'main`\n<script>@everyone', runUrl: 'https://evil.invalid/',
        artifactUrl: meta.artifactUrl + '")<script>' });
    assert.equal(text.includes('<script>'), false);
    assert.equal(text.includes('evil.invalid'), false);
    assert.equal(text.includes('[Log běhu]'), false);
    assert.equal(text.includes('[Snímky v plném'), false);
});

test('a nonzero ratio below rounding precision is not displayed as zero', () => {
    const data = manifest('changed');
    data.snapshots[0].diffPixels = 560;
    data.snapshots[0].diffRatio = 560 / (1440 * 927);
    assert.match(buildComment(data, urls, meta), /méně než 0,1 % pixelů/);
});
