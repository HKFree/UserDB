import { copyFile, lstat, mkdir } from 'node:fs/promises';
import { resolve, dirname, join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';
import { validate } from './validate.mjs';

const gitIdentity = [
    '-c', 'user.name=github-actions[bot]',
    '-c', 'user.email=41898282+github-actions[bot]@users.noreply.github.com',
];

function git(directory, args) {
    return execFileSync('git', ['-C', directory, ...args], {
        encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();
}

async function ensureDirectory(root, relative) {
    let directory = root;
    for (const part of relative.split('/')) {
        directory = join(directory, part);
        await mkdir(directory, { recursive: true });
        if (!(await lstat(directory)).isDirectory()) throw new Error('Destination contains a symlink or non-directory');
    }
}

async function copyReferencedImages(manifest, out, shots, destination) {
    const images = manifest.snapshots.flatMap(snapshot => snapshot.state === 'changed'
        ? [snapshot.head, snapshot.base, snapshot.diff] : [snapshot.head]);
    for (const image of images) {
        const relative = `${destination}/${image.file}`;
        await ensureDirectory(shots, dirname(relative));
        const target = join(shots, relative);
        try {
            if (!(await lstat(target)).isFile()) throw new Error('Destination is not a regular file');
        } catch (error) {
            if (error.code !== 'ENOENT') throw error;
        }
        await copyFile(join(out, image.file), target);
    }
}

function commitImages(shots, destination, pr, headSha) {
    git(shots, ['add', '--sparse', '--', destination]);
    if (git(shots, ['diff', '--cached', '--name-only'])) {
        git(shots, [...gitIdentity, 'commit', '-m', `PR #${pr}: ${headSha.slice(0, 7)}`]);
    }
}

function pushWithRetry(shots) {
    for (let attempt = 1; attempt <= 5; attempt++) {
        try {
            git(shots, ['push', 'origin', 'HEAD:main']);
            return git(shots, ['rev-parse', 'HEAD']);
        } catch (error) {
            if (attempt === 5) throw new Error('Screenshot push failed after 5 attempts', { cause: error });
            git(shots, [...gitIdentity, 'pull', '--rebase', 'origin', 'main']);
        }
    }
}

export async function publish({ out, shots, pr, headSha }) {
    if (!/^[1-9][0-9]*$/.test(String(pr))) throw new Error('Invalid pull request number');
    if (!headSha) throw new Error('Expected head SHA is required');
    const manifest = await validate(out, headSha);
    if (git(shots, ['status', '--porcelain'])) throw new Error('Screenshots checkout must be clean');
    const destination = `pr-${pr}/${manifest.head.sha}`;
    await copyReferencedImages(manifest, out, shots, destination);
    commitImages(shots, destination, pr, headSha);
    return pushWithRetry(shots);
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    const [out, shots, pr, headSha] = process.argv.slice(2);
    console.log(await publish({ out, shots, pr, headSha }));
}
