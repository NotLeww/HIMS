import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
const start = source.indexOf('const procurementWorkspaceTabs');
const end = source.indexOf("Alpine.data('himsToastNotifications'", start);
let createWorkspace;
new Function('Alpine', source.slice(start, end))({ data: (_, factory) => { createWorkspace = factory; } });

const create = () => createWorkspace({
    itemContextUrl: '/inventory/purchases/items/__ITEM__/context',
    items: [{ id: 1, contextLoaded: false }, { id: 2, contextLoaded: false }],
    suppliers: [{ id: 5 }],
});
const response = (stock, price, itemId) => ({
    ok: true,
    redirected: false,
    json: async () => ({
        item: { current_stock: stock },
        supplier_terms: { [`5:${itemId}`]: { unit_cost: price } },
    }),
});

test('item context ignores obsolete selection and reuses a fresh result', async () => {
    const workspace = create();
    let resolveFirst;
    let requests = 0;
    globalThis.fetch = (url) => {
        requests++;
        return url.includes('/1/')
            ? new Promise((resolve) => { resolveFirst = () => resolve(response(7, 80, 1)); })
            : Promise.resolve(response(12, 99, 2));
    };

    workspace.itemId = '1';
    const first = workspace.loadItemContext('1');
    workspace.itemId = '2';
    await workspace.loadItemContext('2');
    resolveFirst();
    await first;

    assert.equal(workspace.items[0].contextLoaded, false);
    assert.equal(workspace.items[1].current_stock, 12);
    assert.equal(workspace.supplierTerms['5:2'].unit_cost, 99);
    assert.equal(workspace.itemContextLoading, false);
    await workspace.loadItemContext('2');
    assert.equal(requests, 2);
});

test('item context failure leaves review blocked and exposes retry', async () => {
    const workspace = create();
    workspace.itemId = '1';
    globalThis.fetch = async () => { throw new Error('offline'); };

    await workspace.loadItemContext('1');
    assert.equal(workspace.items[0].contextLoaded, false);
    assert.match(workspace.itemContextError, /try again/i);
    assert.equal(workspace.itemContextLoading, false);
});
