import { test as base, expect } from '@playwright/test';
import { openMailbox } from './mailbox.mjs';

/** Demo personas of docs/userdb_demo_data.md used by the tests. */
export const personas = {
    vv: 1,
    technik: 20000,
    so: 20002,
    zso: 20004,
    druzstevnik: 20006,
    soRozvetvena: 20012,
    /** Signs in with DRUŽSTEVNÍK only; the SO role of area 201 ended a week ago. */
    byvalySo: 20010,
};

const origin = new URL(process.env.USERDB_E2E_ORIGIN).origin;
const personaCookie = process.env.USERDB_E2E_PERSONA_COOKIE;

async function signInAs(context, persona) {
    await context.addCookies([{ name: personaCookie, value: String(persona), url: origin }]);
}

/** What a page must never do, whatever the test is about. */
class Problems {
    #found = [];
    #allowed = [];

    constructor(context) {
        context.on('console', message => {
            if (message.type() === 'error') this.#add(`console error: ${message.text()} (${message.location().url})`);
        });
        context.on('weberror', error => this.#add(`uncaught exception: ${error.error().message}`));
        context.on('response', response => {
            if (response.status() >= 500) this.#add(`HTTP ${response.status()}: ${response.url()}`);
        });
        // Playwright does not route the hops of a redirect, so they are not blocked. They are still
        // requests of their own here, and one that leaves the local web fails the test.
        context.on('request', request => {
            if (request.redirectedFrom() && new URL(request.url()).origin !== origin) this.blocked(request.url());
        });
    }

    /** A test that provokes an error on purpose names it here, before it happens. */
    allow(pattern) {
        this.#allowed.push(pattern);
    }

    blocked(url) {
        this.#add(`request outside the local web: ${url}`);
    }

    list() {
        return this.#found;
    }

    #add(problem) {
        if (!this.#allowed.some(pattern => pattern.test(problem))) this.#found.push(problem);
    }
}

export const test = base.extend({
    /** The persona signed in when the test starts. */
    persona: [personas.vv, { option: true }],

    context: async ({ context, persona }, use) => {
        await signInAs(context, persona);
        // The Tracy bar of the local web would cover the controls in the corner of the page.
        await context.addInitScript(() => {
            document.addEventListener('DOMContentLoaded', () => {
                const style = document.createElement('style');
                style.textContent = '#tracy-debug { display: none !important; }';
                document.head.append(style);
            });
        });
        await use(context);
    },

    /** Switches the signed-in persona; the next request is made as that person. */
    signInAs: async ({ context }, use) => {
        await use(persona => signInAs(context, persona));
    },

    /** E-mails the web has written since the test started. */
    mailbox: async ({}, use) => {
        await use(await openMailbox());
    },

    problems: [async ({ context }, use) => {
        const problems = new Problems(context);
        // The browser may talk to the web under test only.
        await context.route('**/*', route => {
            const url = route.request().url();
            if (new URL(url).origin === origin) return route.continue();
            problems.blocked(url);
            return route.abort('blockedbyclient');
        });
        await use(problems);
        for (const page of context.pages()) {
            if (page.isClosed()) continue;
            // A test may end right after a navigation. The page it ends on must finish loading, so that
            // its scripts can still fail the test and closing the browser does not wait for its requests.
            await page.waitForLoadState('load');
            expect(new URL(page.url()).origin, 'the test ended outside the local web').toBe(origin);
            await expect(page.locator('#tracy-bs'), `Tracy error page at ${page.url()}`).toHaveCount(0);
        }
        expect(problems.list(), 'errors in the browser or on the server during the test').toEqual([]);
    }, { auto: true }],
});

export { expect };
