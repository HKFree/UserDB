import test from 'node:test';
import assert from 'node:assert/strict';
import { writeFile, unlink, symlink, mkdir, truncate } from 'node:fs/promises';
import { join } from 'node:path';
import { validate } from '../validate.mjs';
import { temporary, artifact, manifest, headSha, png } from './helpers.mjs';

test('accepts a valid artifact and checks the run SHA', async t => {
    const out = await temporary(t);
    const data = await artifact(out);
    assert.deepEqual(await validate(out, headSha), data);
    await assert.rejects(validate(out, 'd'.repeat(40)), /does not match/);
});

for (const [name, mutate, expected] of [
    ['parent id', m => { m.snapshots[0].id = '../escape'; }, /snapshot id/],
    ['slash id', m => { m.snapshots[0].id = 'a/b'; }, /snapshot id/],
    ['unknown state', m => { m.snapshots[0].state = 'surprise'; }, /snapshot state/],
    ['unknown viewport', m => { m.snapshots[0].viewport = 'tablet'; }, /viewport/],
    ['path injection', m => { m.snapshots[0].path = '/x`<script>'; }, /page path/],
    ['file traversal', m => { m.snapshots[0].head.file = '../escape.png'; }, /image path/],
    ['absolute file', m => { m.snapshots[0].head.file = '/tmp/image.png'; }, /image path/],
    ['free base reason', m => { m.base.available = false; m.base.reason = 'arbitrary text'; }, /base reason/],
    ['invalid SHA', m => { m.head.sha = 'ABC'; }, /SHA/],
    ['array SHA', m => { m.head.sha = [headSha]; }, /SHA/],
    ['too wide', m => { m.snapshots[0].head.width = 1441; }, /dimensions/],
    ['too tall', m => { m.snapshots[0].head.height = 8001; }, /dimensions/],
    ['numeric error injection', m => { m.snapshots[0].head.consoleErrors = '<script>'; }, /error count/],
    ['duplicate snapshot', m => { m.snapshots.push(m.snapshots[0]); }, /Duplicate/],
]) {
    test(`rejects ${name}`, async t => {
        const out = await temporary(t);
        const data = await artifact(out);
        mutate(data);
        await writeFile(join(out, 'manifest.json'), JSON.stringify(data));
        await assert.rejects(validate(out), expected);
    });
}

test('rejects missing files, non-PNGs and mismatched PNG dimensions', async t => {
    const out = await temporary(t);
    await artifact(out);
    const file = join(out, 'head/homepage-desktop.png');
    await unlink(file);
    await assert.rejects(validate(out), /Missing image/);
    await writeFile(file, 'not a PNG');
    await assert.rejects(validate(out), /Invalid PNG/);
    await writeFile(file, png(1441, 10));
    await assert.rejects(validate(out), /dimensions/);
});

test('rejects symlink files and symlink directories', async t => {
    const out = await temporary(t);
    await artifact(out);
    const file = join(out, 'head/homepage-desktop.png');
    await unlink(file);
    await symlink('../base/homepage-desktop.png', file);
    await assert.rejects(validate(out), /Not a regular file/);
    await unlink(file);
    await symlink('base', join(out, 'other'));
    await assert.rejects(validate(out), /Not a regular file/);
});

test('service logs of a local run do not count towards the limits', async t => {
    const out = await temporary(t);
    await artifact(out);
    await mkdir(join(out, 'logs'));
    for (let i = 0; i < 64; i++) await writeFile(join(out, 'logs', `service-${i}.log`), '');
    assert.equal((await validate(out, headSha)).snapshots.length, 2);
});

test('rejects more than 64 files, including unreferenced ones', async t => {
    const out = await temporary(t);
    await artifact(out);
    for (let i = 0; i < 64; i++) await writeFile(join(out, `extra-${i}`), '');
    await assert.rejects(validate(out), /Too many files/);
});

test('rejects artifact over 50 MB before reading image content', async t => {
    const out = await temporary(t);
    await artifact(out);
    const file = join(out, 'head/homepage-desktop.png');
    await truncate(file, 50 * 1024 * 1024);
    await assert.rejects(validate(out), /50 MB/);
});

test('rejects a directory used as an image', async t => {
    const out = await temporary(t);
    await artifact(out, manifest());
    const file = join(out, 'head/homepage-desktop.png');
    await unlink(file);
    await mkdir(file);
    await assert.rejects(validate(out), /Unexpected artifact directory/);
});
