import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
const start = source.indexOf('const procurementWorkspaceTabs');
const end = source.indexOf("Alpine.data('himsToastNotifications'", start);
let createWorkspace;
let workspace;
let current;
let history;
const listeners = new Map();
const Alpine = {
    data: (_, factory) => { createWorkspace = factory; },
    mutateDom: (callback) => callback(),
    destroyTree: () => workspace.destroy(),
    initTree: () => {},
};
new Function('Alpine', source.slice(start, end))(Alpine);

const setup = () => {
    history = [];
    window.location = new URL('https://hims.test/inventory/purchases?tab=orders_revisions');
    current = { replaceWith: (node) => { current = node; } };
    workspace = createWorkspace();
    workspace.$watch = () => {};
    workspace.init();
};

globalThis.window = {
    location: new URL('https://hims.test/inventory/purchases'),
    addEventListener: (name, callback) => listeners.set(name, callback),
    removeEventListener: (name, callback) => {
        if (listeners.get(name) === callback) listeners.delete(name);
    },
    history: { pushState: (_, __, url) => {
        history.push(String(url));
        window.location = new URL(String(url));
    } },
};
globalThis.document = { querySelector: () => current };
globalThis.DOMParser = class {
    parseFromString(body) {
        return {
            querySelector: () => body.startsWith('fragment:')
                ? { dataset: { procurementActiveTab: body.slice('fragment:'.length) } }
                : null,
        };
    }
};

test('tab navigation replaces only the workspace and records browser history', async () => {
    setup();
    globalThis.fetch = async (_, options) => {
        assert.equal(options.headers['X-Procurement-Tab'], '1');
        return { ok: true, redirected: false, text: async () => 'fragment:legacy_canvass' };
    };

    await workspace.navigateToTab('legacy_canvass');
    assert.equal(current.dataset.procurementActiveTab, 'legacy_canvass');
    assert.equal(history.length, 1);
    assert.match(window.location.href, /tab=legacy_canvass/);
    assert.equal(listeners.has('popstate'), false);
});

test('failed and obsolete requests keep the current workspace and URL', async () => {
    setup();
    let finishFirst;
    globalThis.fetch = (url) => url.includes('legacy_canvass')
        ? new Promise((resolve) => { finishFirst = () => resolve({ ok: true, redirected: false, text: async () => 'fragment:legacy_canvass' }); })
        : Promise.reject(new Error('offline'));

    const first = workspace.loadTab('https://hims.test/inventory/purchases?tab=legacy_canvass');
    await workspace.loadTab('https://hims.test/inventory/purchases?tab=audit_trail');
    finishFirst();
    await first;

    assert.equal(current.dataset, undefined);
    assert.equal(history.length, 0);
    assert.match(workspace.tabError, /try again/i);
    assert.equal(workspace.failedTabUrl.url, 'https://hims.test/inventory/purchases?tab=audit_trail');
});

test('back navigation fetches the requested tab without adding history', async () => {
    setup();
    globalThis.fetch = async () => ({ ok: true, redirected: false, text: async () => 'fragment:audit_trail' });
    window.location = new URL('https://hims.test/inventory/purchases?tab=audit_trail');

    listeners.get('popstate')();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(current.dataset.procurementActiveTab, 'audit_trail');
    assert.equal(history.length, 0);
});

test('browser navigation to the current tab cancels an unfinished switch', async () => {
    setup();
    let finish;
    globalThis.fetch = () => new Promise((resolve) => {
        finish = () => resolve({ ok: true, redirected: false, text: async () => 'fragment:legacy_canvass' });
    });

    const pending = workspace.loadTab('https://hims.test/inventory/purchases?tab=legacy_canvass');
    listeners.get('popstate')();
    finish();
    await pending;

    assert.equal(current.dataset, undefined);
    assert.equal(history.length, 0);
    assert.equal(workspace.tabLoading, false);
});
