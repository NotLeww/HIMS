import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
const start = source.indexOf("Alpine.data('inventoryReportTabs'");
const end = source.indexOf('const loadingButtons', start);
let createTabs;
new Function('Alpine', source.slice(start, end))({ data: (_, factory) => { createTabs = factory; } });

const panels = Object.fromEntries(['overview', 'valuation', 'movements', 'expiry'].map((tab) => [
    `report-panel-${tab}`, { innerHTML: '' },
]));
globalThis.document = {
    getElementById: (id) => panels[id],
    querySelector: () => null,
};
globalThis.HTMLInputElement = class {};
globalThis.HTMLAnchorElement = class {};
globalThis.window = {
    location: { href: 'http://localhost/inventory/reports' },
    history: {
        pushState: (_, __, url) => { window.location.href = String(url); },
        replaceState: (_, __, url) => { window.location.href = String(url); },
    },
};
globalThis.DOMParser = class {
    parseFromString(body) {
        const [tab, version] = body.split(':');
        return { getElementById: (id) => id === 'report-sections'
            ? { dataset: { reportVersion: version } }
            : id === `report-panel-${tab}` ? { innerHTML: `<p>${tab}</p>` } : null };
    }
};

const response = (tab) => ({ ok: true, redirected: false, text: async () => `${tab}:v1` });

test('rapid switches ignore obsolete responses and valid panels are reused', async () => {
    const tabs = createTabs({ initialTab: 'overview', initialVersion: 'v1' });
    tabs.$nextTick = (callback) => callback();
    tabs.animateActiveTab = () => {};
    tabs.pollFreshness = () => {};

    let resolveValuation;
    let requests = 0;
    globalThis.fetch = (url) => {
        requests++;
        const tab = url.searchParams.get('section');
        return tab === 'valuation'
            ? new Promise((resolve) => { resolveValuation = () => resolve(response(tab)); })
            : Promise.resolve(response(tab));
    };

    const oldRequest = tabs.selectTab('valuation');
    await tabs.selectTab('movements');
    resolveValuation();
    await oldRequest;
    assert.equal(tabs.activeTab, 'movements');
    assert.equal(panels['report-panel-valuation'].innerHTML, '');
    assert.equal(panels['report-panel-movements'].innerHTML, '<p>movements</p>');

    await tabs.selectTab('overview');
    await tabs.selectTab('movements');
    assert.equal(requests, 2);
});

test('failed section load retains the current panel and offers retry', async () => {
    const tabs = createTabs({ initialTab: 'overview', initialVersion: 'v1' });
    globalThis.fetch = async () => { throw new Error('offline'); };

    await tabs.selectTab('expiry');
    assert.equal(tabs.activeTab, 'overview');
    assert.equal(tabs.failedTab, 'expiry');
    assert.match(tabs.loadError, /try again/i);
    assert.equal(tabs.loadingTab, null);
});
