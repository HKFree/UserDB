import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { writeFile, readFile, unlink, chmod } from 'node:fs/promises';
import { publish } from '../publish.mjs';
import { temporary, artifact, headSha, manifest } from './helpers.mjs';

function git(directory, ...args) {
    return execFileSync('git', ['-C', directory, ...args], {
        encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();
}

async function repositories(t) {
    const root = await temporary(t);
    const bare = join(root, 'origin.git');
    const shots = join(root, 'shots');
    git(root, 'init', '--bare', '--initial-branch=main', bare);
    git(root, 'clone', bare, shots);
    git(shots, 'config', 'user.name', 'Test');
    git(shots, 'config', 'user.email', 'test@example.invalid');
    await writeFile(join(shots, 'README.md'), 'Screenshot fixtures\n');
    git(shots, 'add', 'README.md');
    git(shots, 'commit', '-m', 'Initial');
    git(shots, 'push', 'origin', 'main');
    git(shots, 'sparse-checkout', 'set', `pr-42/${headSha}`);
    const out = join(root, 'artifact');
    await artifact(out, manifest('changed'));
    await writeFile(join(out, 'unreferenced.txt'), 'must not be copied');
    return { root, bare, shots, out, pr: 42, headSha };
}

test('first publish copies only linked images; identical re-run makes no empty commit', async t => {
    const options = await repositories(t);
    const commit = await publish(options);
    assert.equal(git(options.bare, 'rev-parse', 'main'), commit);
    const files = git(options.bare, 'ls-tree', '-r', '--name-only', 'main').split('\n');
    assert.deepEqual(files, ['README.md', ...[
        'base/homepage-desktop.png', 'diff/homepage-desktop.png',
        'head/homepage-desktop.png', 'head/homepage-mobile.png',
    ].map(file => `pr-42/${headSha}/${file}`)]);
    assert.equal(await publish(options), commit);
    assert.equal(git(options.bare, 'rev-list', '--count', 'main'), '2');
});

test('concurrent push of another directory is rebased and retried', async t => {
    const options = await repositories(t);
    const other = join(options.root, 'other');
    git(options.root, 'clone', options.bare, other);
    const hook = join(options.shots, '.git/hooks/pre-push');
    await writeFile(hook, `#!/usr/bin/env node
const {execFileSync} = require('node:child_process');
const {mkdirSync, writeFileSync, unlinkSync} = require('node:fs');
const git = (...args) => execFileSync('git', ['-C', ${JSON.stringify(other)}, ...args]);
unlinkSync(${JSON.stringify(hook)});
mkdirSync(${JSON.stringify(join(other, 'pr-99'))});
writeFileSync(${JSON.stringify(join(other, 'pr-99/image.txt'))}, 'concurrent');
git('add', '.');
git('-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-m', 'Concurrent');
git('push', 'origin', 'main');
`);
    await chmod(hook, 0o755);
    const commit = await publish(options);
    assert.equal(git(options.bare, 'rev-parse', 'main'), commit);
    assert.equal(git(options.bare, 'show', 'main:pr-99/image.txt'), 'concurrent');
    assert.equal(git(options.bare, 'rev-list', '--count', 'main'), '3');
});

test('missing referenced file fails without changing the destination repository', async t => {
    const options = await repositories(t);
    await unlink(join(options.out, 'diff/homepage-desktop.png'));
    await assert.rejects(publish(options), /Missing image/);
    assert.equal(git(options.bare, 'rev-list', '--count', 'main'), '1');
    assert.equal(git(options.shots, 'status', '--porcelain'), '');
});

test('a rejecting remote is attempted at most five times', async t => {
    const options = await repositories(t);
    const counter = join(options.root, 'attempts.txt');
    const hook = join(options.bare, 'hooks/pre-receive');
    await writeFile(hook, [
        '#!/usr/bin/env node',
        `require('node:fs').appendFileSync(${JSON.stringify(counter)}, 'attempt\\n');`,
        'process.exit(1);', '',
    ].join('\n'));
    await chmod(hook, 0o755);
    await assert.rejects(publish(options), /failed after 5 attempts/);
    assert.equal(await readFile(counter, 'utf8'), 'attempt\n'.repeat(5));
    assert.equal(git(options.bare, 'rev-list', '--count', 'main'), '1');
});
