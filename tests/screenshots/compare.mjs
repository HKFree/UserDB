import { appendFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

// Console errors are only recorded: a blocked foreign request or a missing favicon logs one on a healthy page.
function hasError(snapshot) {
    return snapshot.failed || snapshot.status < 200 || snapshot.status >= 400 || snapshot.tracy;
}

function pad(image, width, height) {
    const padded = new PNG({ width, height });
    padded.data.fill(255);
    PNG.bitblt(image, padded, 0, 0, image.width, image.height, 0, 0);
    return padded;
}

export function compareImages(baseBuffer, headBuffer, maxDiffPixels = 0) {
    const base = PNG.sync.read(baseBuffer);
    const head = PNG.sync.read(headBuffer);
    const width = Math.max(base.width, head.width);
    const height = Math.max(base.height, head.height);
    const diff = new PNG({ width, height });
    const diffPixels = pixelmatch(pad(base, width, height).data, pad(head, width, height).data,
        diff.data, width, height, { threshold: 0.1, includeAA: false });
    const changed = base.width !== head.width || base.height !== head.height || diffPixels > maxDiffPixels;
    return { state: changed ? 'changed' : 'unchanged', diffPixels, diffRatio: diffPixels / (width * height),
        width, height, buffer: changed ? PNG.sync.write(diff) : null };
}

function captureDetails(record) {
    const { id, path, viewport, maxDiffPixels, ...details } = record;
    return details;
}

export async function compare({ out, headSha, baseSha, baseReason = null }) {
    baseReason ||= null;
    const head = JSON.parse(await readFile(resolve(out, 'head/capture.json'), 'utf8'));
    const base = baseReason ? [] : JSON.parse(await readFile(resolve(out, 'base/capture.json'), 'utf8'));
    const manifest = { schemaVersion: 1, head: { sha: headSha },
        base: { sha: baseSha, available: !baseReason, reason: baseReason }, snapshots: [] };
    for (const record of head) {
        const previous = base.find(item => item.id === record.id && item.viewport === record.viewport);
        if (!baseReason && !previous) throw new Error(`Missing base capture: ${record.id}-${record.viewport}`);
        const snapshot = {
            id: record.id, path: record.path, viewport: record.viewport, maxDiffPixels: record.maxDiffPixels,
            state: 'unchanged', diffPixels: 0, diffRatio: 0,
            head: captureDetails(record), base: previous ? captureDetails(previous) : null, diff: null,
        };
        if (hasError(record)) snapshot.state = 'error';
        else if (previous?.status === 404) snapshot.state = 'new';
        else if (previous && hasError(previous)) snapshot.state = 'base-error';
        else if (previous) {
            const result = compareImages(await readFile(resolve(out, previous.file)),
                await readFile(resolve(out, record.file)), record.maxDiffPixels);
            snapshot.state = result.state;
            snapshot.diffPixels = result.diffPixels;
            snapshot.diffRatio = result.diffRatio;
            if (result.buffer) {
                await mkdir(resolve(out, 'diff'), { recursive: true });
                snapshot.diff = {
                    file: `diff/${record.id}-${record.viewport}.png`, width: result.width, height: result.height,
                };
                await writeFile(resolve(out, snapshot.diff.file), result.buffer);
            }
        }
        manifest.snapshots.push(snapshot);
    }
    await writeFile(resolve(out, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
    return manifest;
}

function counts(manifest) {
    return {
        total: manifest.snapshots.length,
        changed: manifest.snapshots.filter(item => item.state === 'changed').length,
        new: manifest.snapshots.filter(item => item.state === 'new').length,
        error: manifest.snapshots.filter(item => ['error', 'base-error'].includes(item.state)).length,
    };
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    const [out, headSha, baseSha, baseReason] = process.argv.slice(2);
    const manifest = await compare({ out, headSha, baseSha, baseReason });
    const result = counts(manifest);
    const summary = `${result.changed} changed, ${result.new} new, ${result.error} error snapshots; `
        + `${result.total} total.`;
    console.log(summary);
    if (process.env.GITHUB_STEP_SUMMARY) await appendFile(process.env.GITHUB_STEP_SUMMARY, summary + '\n');
    console.log(`Output: ${resolve(out)}`);
    for (const snapshot of manifest.snapshots.filter(item => item.state === 'changed')) {
        console.log(`Changed: ${snapshot.path} ${snapshot.viewport}, `
            + `${snapshot.diffPixels} pixels, ${snapshot.diff.file}`);
    }
}
