import { readdir, readFile } from 'node:fs/promises';
import { join } from 'node:path';

const directory = process.env.USERDB_E2E_MAIL_DIR;

function decodeBytes(text, encoding) {
    if (/^b(ase64)?$/i.test(encoding)) return Buffer.from(text, 'base64');
    const unescaped = text.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)));
    return Buffer.from(unescaped, 'latin1');
}

/** Decodes the encoded words of a header (RFC 2047). */
function decodeHeader(value) {
    return value.replace(/=\?([^?]+)\?([bq])\?([^?]*)\?=(?:\s+(?==\?))?/gi, (_, charset, encoding, text) =>
        new TextDecoder(charset).decode(decodeBytes(encoding.toLowerCase() === 'q' ? text.replace(/_/g, ' ') : text, encoding)));
}

function parseHeaders(block) {
    const headers = {};
    for (const line of block.replace(/\r?\n[ \t]+/g, ' ').split(/\r?\n/)) {
        const separator = line.indexOf(':');
        if (separator > 0) headers[line.slice(0, separator).toLowerCase()] = decodeHeader(line.slice(separator + 1).trim());
    }
    return headers;
}

/** Returns the headers of a MIME entity and the decoded text of all its textual parts. */
function parseEntity(raw) {
    const [, head, body = ''] = raw.match(/^([\s\S]*?)\r?\n\r?\n([\s\S]*)$/) ?? [null, raw];
    const headers = parseHeaders(head);
    const type = headers['content-type'] ?? 'text/plain';
    const boundary = type.match(/boundary="?([^";]+)"?/i)?.[1];
    if (boundary) {
        const parts = body.split(`--${boundary}`).slice(1, -1);
        return { headers, text: parts.map(part => parseEntity(part.replace(/^\r?\n/, '')).text).join('\n') };
    }
    if (!/^text\//i.test(type)) return { headers, text: '' };
    const encoding = headers['content-transfer-encoding'] ?? '8bit';
    const bytes = /^(base64|quoted-printable)$/i.test(encoding) ? decodeBytes(body, encoding) : Buffer.from(body, 'utf8');
    return { headers, text: new TextDecoder(type.match(/charset="?([^";]+)"?/i)?.[1] ?? 'utf-8').decode(bytes) };
}

async function files() {
    try {
        return (await readdir(directory)).filter(name => name.endsWith('.eml')).sort();
    } catch (error) {
        if (error.code === 'ENOENT') return [];
        throw error;
    }
}

/**
 * E-mails the local web stored instead of sending them (scripts/UserdbLocalMailer.php).
 * Only those written after the mailbox was opened are returned, so tests do not see each other's mail.
 */
export async function openMailbox() {
    const before = new Set(await files());
    return {
        /** @returns {Promise<Array<{to: string, subject: string, text: string, headers: Record<string, string>}>>} */
        async received() {
            const messages = [];
            for (const name of await files()) {
                if (before.has(name)) continue;
                const { headers, text } = parseEntity(await readFile(join(directory, name), 'utf8'));
                messages.push({ to: headers.to ?? '', subject: headers.subject ?? '', text, headers });
            }
            return messages;
        },
    };
}
