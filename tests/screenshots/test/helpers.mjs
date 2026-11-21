import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { PNG } from 'pngjs';

export const headSha = 'a'.repeat(40);
export const baseSha = 'b'.repeat(40);
export const urls = { repository: 'HKFree/UserDB-screenshots', commit: 'c'.repeat(40), pr: 42 };
export const meta = { repository: 'HKFree/UserDB', runId: '123', baseBranch: 'master',
    runUrl: 'https://github.com/HKFree/UserDB/actions/runs/123',
    artifactUrl: 'https://github.com/HKFree/UserDB/actions/runs/123/artifacts/456' };

export async function temporary(t) {
    const directory = await mkdtemp(join(tmpdir(), 'userdb-screenshots-test-'));
    t.after(() => rm(directory, { recursive: true, force: true }));
    return directory;
}

export function png(width = 10, height = 10, blackPixel = false) {
    const image = new PNG({ width, height });
    image.data.fill(255);
    if (blackPixel) image.data.fill(0, 0, 3);
    return PNG.sync.write(image);
}

export function details(label, id = 'homepage', viewport = 'desktop') {
    return { file: `${label}/${id}-${viewport}.png`, width: 10, height: 10, truncated: false,
        clippedWidth: false, status: 200, finalUrl: 'http://127.0.0.1:4500/', tracy: false,
        consoleErrors: 0, failed: false, blockedRequests: 0 };
}

export function snapshot(state = 'unchanged', id = 'homepage', viewport = 'desktop') {
    const item = { id, path: '/', viewport, maxDiffPixels: 0, state, diffPixels: 0, diffRatio: 0,
        head: details('head', id, viewport), base: details('base', id, viewport), diff: null };
    if (state === 'changed') {
        item.diffPixels = 1;
        item.diffRatio = 0.01;
        item.diff = { file: `diff/${id}-${viewport}.png`, width: 10, height: 10 };
    }
    if (state === 'new') item.base.status = 404;
    if (state === 'error') { item.head.status = 500; item.head.tracy = true; }
    if (state === 'base-error') { item.base.status = 500; item.base.consoleErrors = 2; }
    return item;
}

export function manifest(state = 'unchanged') {
    return { schemaVersion: 1, head: { sha: headSha }, base: { sha: baseSha, available: true, reason: null },
        snapshots: [snapshot(state), snapshot('unchanged', 'homepage', 'mobile')] };
}

export async function artifact(directory, data = manifest()) {
    await mkdir(directory, { recursive: true });
    await writeFile(join(directory, 'manifest.json'), JSON.stringify(data));
    for (const item of data.snapshots) {
        for (const image of [item.head, item.base, item.diff].filter(Boolean)) {
            await mkdir(join(directory, image.file.split('/')[0]), { recursive: true });
            await writeFile(join(directory, image.file), png(image.width, image.height));
        }
    }
    return data;
}
