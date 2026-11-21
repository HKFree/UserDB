import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { validate } from './validate.mjs';
import { baseReasons, imagePattern, pathPattern, shaPattern } from './schema.mjs';

const marker = '<!-- userdb-screenshots -->';
const repoPattern = /^[A-Za-z0-9_.-]{1,100}\/[A-Za-z0-9_.-]{1,100}$/;

function safeMeta(meta) {
    const repo = repoPattern.test(meta.repository ?? '') ? meta.repository : '';
    const runId = /^[1-9][0-9]*$/.test(String(meta.runId)) ? String(meta.runId) : '';
    const runUrl = repo && runId ? `https://github.com/${repo}/actions/runs/${runId}` : '';
    const artifactPrefix = `${runUrl}/artifacts/`;
    const artifactUrl = runUrl && typeof meta.artifactUrl === 'string' && meta.artifactUrl.startsWith(artifactPrefix)
        && /^[0-9]+$/.test(meta.artifactUrl.slice(artifactPrefix.length)) ? meta.artifactUrl : '';
    return { branch: String(meta.baseBranch ?? '').replace(/[^A-Za-z0-9._/-]/g, '').slice(0, 128) || 'master',
        runUrl: meta.runUrl === runUrl ? runUrl : '', artifactUrl };
}

function links(meta) {
    return [meta.runUrl ? `[Log běhu](${meta.runUrl})` : '',
        meta.artifactUrl ? `[Snímky v plném rozlišení](${meta.artifactUrl})` : ''].filter(Boolean).join(' · ');
}

function imageUrl(file, urls, manifest) {
    if (!repoPattern.test(urls.repository) || !shaPattern.test(urls.commit)
        || !/^[1-9][0-9]*$/.test(String(urls.pr))
        || !imagePattern.test(file)) {
        throw new Error('Invalid image URL metadata');
    }
    const directory = `pr-${urls.pr}/${manifest.head.sha}`;
    return `https://raw.githubusercontent.com/${urls.repository}/${urls.commit}/${directory}/${file}`;
}

const viewportNames = { desktop: 'desktop', mobile: 'mobil' };

// The alt text is built from the validated path and fixed words only.
function preview(image, width, snapshot, version, urls, manifest) {
    const url = imageUrl(image.file, urls, manifest);
    const alt = `${snapshot.path} ${viewportNames[snapshot.viewport]}${version ? ', ' + version : ''}`;
    return `<a href="${url}"><img src="${url}" width="${width}" alt="${alt}"></a>`;
}

function annotation(snapshot) {
    const notes = [];
    if (snapshot.state === 'new') notes.push('nová stránka');
    if (snapshot.head.truncated) notes.push('oříznuto na 8000 px');
    if (snapshot.head.clippedWidth) notes.push('šířka oříznuta na 1440 px');
    return notes.length ? '<br>' + notes.join(', ') : '';
}

function errorLine(snapshot) {
    const base = snapshot.state === 'base-error';
    const details = base ? snapshot.base : snapshot.head;
    // Status 0 means the navigation itself failed, so there is no HTTP answer to report.
    const diagnostics = details.status ? [`HTTP ${details.status}`] : [];
    if (details.tracy) diagnostics.push('Tracy');
    if (details.failed) diagnostics.push('načtení stránky nebo pořízení snímku selhalo');
    if (details.consoleErrors) diagnostics.push(`chyby konzole: ${details.consoleErrors}`);
    const label = `\`${snapshot.path}\` ${viewportNames[snapshot.viewport]}${base ? ' (výchozí verze)' : ''}`;
    return `- ${label}: ${diagnostics.join(', ')}`;
}

function bounded(text) {
    if (text.length >= 65_536) throw new Error('Comment exceeds GitHub limit');
    return text;
}

export function buildFailureComment(headSha, meta) {
    if (!shaPattern.test(headSha)) throw new Error('Invalid head SHA');
    return `${marker}\n### Náhledy stránek\n\nNáhledy commitu \`${headSha.slice(0, 7)}\` se nepodařilo pořídit. `
        + `${links({ ...safeMeta(meta), artifactUrl: '' })}\n`;
}

function validateCommentValues(manifest) {
    // Callers pass a validated manifest. Guard every rendered artifact value as well, keeping this function pure.
    if (!shaPattern.test(manifest.head.sha) || !shaPattern.test(manifest.base.sha)) throw new Error('Invalid SHA');
    if (!manifest.base.available && !Object.hasOwn(baseReasons, manifest.base.reason)) {
        throw new Error('Invalid base reason');
    }
    for (const snapshot of manifest.snapshots) {
        if (!pathPattern.test(snapshot.path) || !Object.hasOwn(viewportNames, snapshot.viewport)
            || !Number.isFinite(snapshot.diffRatio) || snapshot.diffRatio < 0 || snapshot.diffRatio > 1) {
            throw new Error('Invalid comment snapshot');
        }
        for (const details of [snapshot.head, snapshot.base].filter(Boolean)) {
            if (!Number.isSafeInteger(details.status) || !Number.isSafeInteger(details.consoleErrors)) {
                throw new Error('Invalid error diagnostics');
            }
        }
    }
}

function summary(manifest, changed, errors) {
    const baseSha = manifest.base.sha.slice(0, 7);
    if (!manifest.base.available) {
        return `Porovnání chybí, verzi \`${baseSha}\` se nepodařilo spustit: ${baseReasons[manifest.base.reason]}.`;
    }
    if (changed.length || errors.length || manifest.snapshots.some(item => item.state === 'new')) {
        return `**Změněno ${changed.length} z ${manifest.snapshots.length} snímků.**`;
    }
    return `Beze změny proti \`${baseSha}\`.`;
}

function pixelPercentage(ratio) {
    const rounded = (ratio * 100).toFixed(1);
    return ratio > 0 && rounded === '0.0' ? 'méně než 0,1 % pixelů' : `${rounded.replace('.', ',')} % pixelů`;
}

function changesTable(changed, urls, manifest) {
    if (!changed.length) return [];
    const lines = ['', '#### Změny', '| Stránka | Před | Po | Rozdíl |', '|---|---|---|---|'];
    for (const snapshot of changed) {
        const previews = [[snapshot.base, 'před'], [snapshot.head, 'po'], [snapshot.diff, 'rozdíl']]
            .map(([image, version]) => preview(image, 240, snapshot, version, urls, manifest));
        lines.push(`| \`${snapshot.path}\`<br>${viewportNames[snapshot.viewport]}, `
            + `${pixelPercentage(snapshot.diffRatio)}${annotation(snapshot)} | ${previews.join(' | ')} |`);
    }
    return lines;
}

function allPagesTable(manifest, urls) {
    const lines = ['', '#### Všechny stránky', '| Stránka | Desktop | Mobil |', '|---|---|---|'];
    for (const id of new Set(manifest.snapshots.map(item => item.id))) {
        const snapshots = manifest.snapshots.filter(item => item.id === id);
        const cells = ['desktop', 'mobile'].map(viewport => {
            const snapshot = snapshots.find(item => item.viewport === viewport);
            if (!snapshot) return '';
            const width = viewport === 'desktop' ? 320 : 120;
            return preview(snapshot.head, width, snapshot, '', urls, manifest) + annotation(snapshot);
        });
        lines.push(`| \`${snapshots[0].path}\` | ${cells.join(' | ')} |`);
    }
    return lines;
}

export function buildComment(manifest, urls, meta) {
    validateCommentValues(manifest);
    const safe = safeMeta(meta);
    const changed = manifest.snapshots.filter(item => item.state === 'changed');
    const errors = manifest.snapshots.filter(item => ['error', 'base-error'].includes(item.state));
    const introduction = `Commit \`${manifest.head.sha.slice(0, 7)}\` proti \`${manifest.base.sha.slice(0, 7)}\` `
        + `(společný předek s \`${safe.branch}\`). Snímky jsou z GitHub runneru se základními demo `
        + `daty bez objemu, ne z testovací instance. ${links(safe)}`;
    const lines = [marker, '### Náhledy stránek', '', introduction, '', summary(manifest, changed, errors)];
    if (errors.length) lines.push('', '#### Chyby', ...errors.map(errorLine));
    lines.push(...changesTable(changed, urls, manifest), ...allPagesTable(manifest, urls));
    return bounded(lines.join('\n') + '\n');
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    const [mode, source, urlsJson, metaJson] = process.argv.slice(2);
    if (mode === 'failure') process.stdout.write(buildFailureComment(source, JSON.parse(urlsJson)));
    else if (mode === 'manifest') {
        process.stdout.write(buildComment(await validate(source), JSON.parse(urlsJson), JSON.parse(metaJson)));
    }
    else throw new Error('Usage: comment.mjs manifest <out> <urls JSON> <meta JSON> | failure <head SHA> <meta JSON>');
}
