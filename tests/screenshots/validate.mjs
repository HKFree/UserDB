import { lstat, readdir, readFile, realpath } from 'node:fs/promises';
import { resolve, join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { baseReasons, idPattern, pathPattern, shaPattern } from './schema.mjs';

const states = ['unchanged', 'changed', 'new', 'error', 'base-error'];
const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);

function requireValid(condition, message) {
    if (!condition) throw new Error(message);
}

function exactKeys(object, keys, name) {
    requireValid(object !== null && typeof object === 'object' && !Array.isArray(object), `Invalid ${name}`);
    requireValid(Object.keys(object).sort().join(',') === [...keys].sort().join(','), `Invalid ${name} fields`);
}

function integer(value, min, max) {
    return Number.isSafeInteger(value) && value >= min && value <= max;
}

function dimensions(image) {
    requireValid(integer(image.width, 1, 1440) && integer(image.height, 1, 8000), 'Invalid image dimensions');
}

function captureDetails(details, label, filename) {
    exactKeys(details, ['file', 'width', 'height', 'truncated', 'clippedWidth', 'status', 'finalUrl', 'tracy',
        'consoleErrors', 'failed', 'blockedRequests'], `${label} capture`);
    requireValid(details.file === `${label}/${filename}`, 'Invalid image path');
    dimensions(details);
    for (const key of ['truncated', 'clippedWidth', 'tracy', 'failed']) {
        requireValid(typeof details[key] === 'boolean', `Invalid ${key}`);
    }
    requireValid(details.status === 0 || integer(details.status, 100, 599), 'Invalid HTTP status');
    requireValid(integer(details.consoleErrors, 0, 1_000_000), 'Invalid console error count');
    requireValid(integer(details.blockedRequests, 0, 1_000_000), 'Invalid blocked request count');
    requireValid(typeof details.finalUrl === 'string' && details.finalUrl.length <= 4096, 'Invalid final URL');
}

function validateVersions(manifest, expectedHead) {
    exactKeys(manifest, ['schemaVersion', 'head', 'base', 'snapshots'], 'manifest');
    requireValid(manifest.schemaVersion === 1, 'Unsupported manifest schema');
    exactKeys(manifest.head, ['sha'], 'head');
    exactKeys(manifest.base, ['sha', 'available', 'reason'], 'base');
    requireValid(typeof manifest.head.sha === 'string' && typeof manifest.base.sha === 'string'
        && shaPattern.test(manifest.head.sha) && shaPattern.test(manifest.base.sha), 'Invalid SHA');
    requireValid(!expectedHead || manifest.head.sha === expectedHead, 'Manifest head does not match this run');
    requireValid(typeof manifest.base.available === 'boolean', 'Invalid base availability');
    const validReason = typeof manifest.base.reason === 'string' && Object.hasOwn(baseReasons, manifest.base.reason);
    requireValid(manifest.base.available ? manifest.base.reason === null : validReason, 'Invalid base reason');
}

function validateSnapshot(snapshot, baseAvailable) {
    exactKeys(snapshot, ['id', 'path', 'viewport', 'maxDiffPixels', 'state', 'diffPixels', 'diffRatio',
        'head', 'base', 'diff'], 'snapshot');
    requireValid(typeof snapshot.id === 'string' && idPattern.test(snapshot.id), 'Invalid snapshot id');
    requireValid(typeof snapshot.path === 'string' && pathPattern.test(snapshot.path), 'Invalid page path');
    requireValid(['desktop', 'mobile'].includes(snapshot.viewport), 'Invalid viewport');
    requireValid(states.includes(snapshot.state), 'Invalid snapshot state');
    requireValid(integer(snapshot.maxDiffPixels, 0, 1440 * 8000), 'Invalid pixel allowance');
    requireValid(integer(snapshot.diffPixels, 0, 1440 * 8000), 'Invalid pixel count');
    requireValid(typeof snapshot.diffRatio === 'number' && Number.isFinite(snapshot.diffRatio)
        && snapshot.diffRatio >= 0 && snapshot.diffRatio <= 1, 'Invalid pixel ratio');
    const filename = `${snapshot.id}-${snapshot.viewport}.png`;
    captureDetails(snapshot.head, 'head', filename);
    if (baseAvailable) {
        captureDetails(snapshot.base, 'base', filename);
    } else {
        requireValid(snapshot.base === null && ['unchanged', 'error'].includes(snapshot.state),
            'Invalid unavailable base snapshot');
    }
    validateDiff(snapshot, filename);
    return [snapshot.head, snapshot.base, snapshot.diff].filter(Boolean);
}

function validateDiff(snapshot, filename) {
    if (snapshot.state !== 'changed') {
        requireValid(snapshot.diff === null, 'Unexpected diff');
        return;
    }
    exactKeys(snapshot.diff, ['file', 'width', 'height'], 'diff');
    requireValid(snapshot.diff.file === `diff/${filename}`, 'Invalid diff path');
    dimensions(snapshot.diff);
    requireValid(snapshot.diff.width === Math.max(snapshot.base.width, snapshot.head.width)
        && snapshot.diff.height === Math.max(snapshot.base.height, snapshot.head.height), 'Invalid diff dimensions');
}

function validateManifest(manifest, expectedHead) {
    validateVersions(manifest, expectedHead);
    requireValid(Array.isArray(manifest.snapshots) && manifest.snapshots.length > 0 && manifest.snapshots.length <= 63,
        'Invalid snapshot count');
    const seen = new Set();
    const paths = new Map();
    const files = [];
    for (const snapshot of manifest.snapshots) {
        files.push(...validateSnapshot(snapshot, manifest.base.available));
        const key = `${snapshot.id}-${snapshot.viewport}`;
        requireValid(!seen.has(key), 'Duplicate snapshot');
        seen.add(key);
        requireValid(!paths.has(snapshot.id) || paths.get(snapshot.id) === snapshot.path, 'Inconsistent page path');
        paths.set(snapshot.id, snapshot.path);
    }
    requireValid(files.length <= 63, 'Too many files');
    return files;
}

async function inventory(directory, result = new Map(), prefix = '') {
    for (const name of await readdir(directory)) {
        const path = join(directory, name);
        const relative = prefix + name;
        const stat = await lstat(path);
        if (stat.isDirectory()) {
            // A local run keeps its service logs next to the images. They are never published and do not count.
            if (prefix === '' && name === 'logs') continue;
            requireValid(prefix === '' && ['head', 'base', 'diff'].includes(name), 'Unexpected artifact directory');
            await inventory(path, result, relative + '/');
        } else {
            requireValid(stat.isFile(), `Not a regular file: ${relative}`);
            result.set(relative, stat.size);
            requireValid(result.size <= 64, 'Too many files');
            const bytes = [...result.values()].reduce((sum, size) => sum + size, 0);
            requireValid(bytes <= 50 * 1024 * 1024, 'Artifact exceeds 50 MB');
        }
    }
    return result;
}

export async function validate(out, expectedHead) {
    requireValid((await lstat(out)).isDirectory(), 'Artifact is not a directory');
    const root = await realpath(out);
    const files = await inventory(root);
    requireValid(files.has('manifest.json') && files.get('manifest.json') <= 1024 * 1024,
        'Missing or oversized manifest');
    const manifest = JSON.parse(await readFile(join(root, 'manifest.json'), 'utf8'));
    for (const image of validateManifest(manifest, expectedHead)) {
        requireValid(files.has(image.file), `Missing image: ${image.file}`);
        const data = await readFile(join(root, image.file));
        requireValid(data.length >= 33 && data.subarray(0, 8).equals(signature)
            && data.readUInt32BE(8) === 13 && data.toString('ascii', 12, 16) === 'IHDR', `Invalid PNG: ${image.file}`);
        requireValid(data.readUInt32BE(16) === image.width && data.readUInt32BE(20) === image.height,
            `PNG dimensions do not match manifest: ${image.file}`);
    }
    return manifest;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    const manifest = await validate(process.argv[2], process.argv[3]);
    console.log(`Valid manifest: ${manifest.snapshots.length} snapshots, head ${manifest.head.sha}`);
}
