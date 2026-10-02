const DB_NAME = 'hims-offline';
const STORE_NAME = 'warehouse-scans';

const openDatabase = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => request.result.createObjectStore(STORE_NAME, { keyPath: 'id' });
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

const storedScans = async () => {
    const database = await openDatabase();
    return new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME).objectStore(STORE_NAME).getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    }).finally(() => database.close());
};

const saveScan = async (scan) => {
    const database = await openDatabase();
    return new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).put(scan);
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
    }).finally(() => database.close());
};

const deleteScan = async (id) => {
    const database = await openDatabase();
    return new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).delete(id);
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
    }).finally(() => database.close());
};

const notify = (type, message) => window.dispatchEvent(new CustomEvent('toast', {
    detail: { type, title: type === 'success' ? 'SUCCESS' : type === 'error' ? 'ERROR' : 'NOTICE', message },
}));

document.addEventListener('DOMContentLoaded', () => {
    const userId = document.body.dataset.authUserId;
    if (! userId || ! ('indexedDB' in window)) return;

    const form = document.querySelector('[data-offline-sync="warehouse-scan"]');
    const status = document.querySelector('[data-offline-sync-status]');
    const message = status?.querySelector('[data-offline-sync-message]');
    const retry = status?.querySelector('[data-offline-sync-retry]');
    let syncing = false;
    let lastError = '';

    const pendingScans = async () => (await storedScans())
        .filter((scan) => String(scan.userId) === String(userId))
        .sort((left, right) => left.createdAt.localeCompare(right.createdAt));

    const renderStatus = async () => {
        if (! status || ! message) return;
        const count = (await pendingScans()).length;
        status.hidden = navigator.onLine && count === 0 && ! lastError;
        message.textContent = ! navigator.onLine
            ? `Offline. ${count ? `${count} scan${count === 1 ? '' : 's'} pending on this device.` : 'New warehouse scans will remain pending on this device.'}`
            : syncing
                ? `Synchronizing ${count} pending scan${count === 1 ? '' : 's'}...`
                : lastError
                    ? `${count} scan${count === 1 ? '' : 's'} pending. ${lastError}`
                    : `${count} scan${count === 1 ? '' : 's'} pending synchronization.`;
        retry?.toggleAttribute('hidden', ! navigator.onLine || syncing || count === 0);
    };

    const send = async (scan) => {
        const response = await fetch(scan.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Idempotency-Key': scan.id,
            },
            body: JSON.stringify({ scan_value: scan.scanValue }),
        });
        const payload = await response.json().catch(() => ({}));
        if (! response.ok) throw new Error(payload.message || `Synchronization failed (${response.status}).`);
    };

    const performSynchronization = async () => {
        if (! navigator.onLine || syncing) return;
        const scans = await pendingScans();
        if (scans.length === 0) return;

        syncing = true;
        lastError = '';
        await renderStatus();

        try {
            for (const scan of scans) {
                await send(scan);
                await deleteScan(scan.id);
            }
            notify('success', 'Pending warehouse scans synchronized successfully.');
            if (form) window.location.reload();
        } catch (error) {
            lastError = error instanceof Error ? error.message : 'Synchronization failed. Retry when the server is available.';
            notify('error', lastError);
        } finally {
            syncing = false;
            await renderStatus();
        }
    };

    const synchronize = () => navigator.locks?.request
        ? navigator.locks.request(`hims-offline-sync-${userId}`, performSynchronization)
        : performSynchronization();

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (! form.reportValidity()) return;

        const input = form.elements.namedItem('scan_value');
        const scanValue = input instanceof HTMLInputElement ? input.value.trim() : '';
        if (! scanValue) return;

        try {
            await saveScan({
                id: crypto.randomUUID(),
                userId,
                endpoint: form.dataset.syncEndpoint,
                scanValue,
                createdAt: new Date().toISOString(),
            });
            input.value = '';
            lastError = '';
            await renderStatus();
            if (navigator.onLine) await synchronize();
        } catch {
            notify('error', 'The scan could not be saved on this device. It was not sent to the server.');
        }
    });

    retry?.addEventListener('click', synchronize);
    window.addEventListener('online', synchronize);
    window.addEventListener('offline', renderStatus);
    renderStatus().then(synchronize).catch(() => {
        notify('error', 'Pending warehouse scans could not be read from this device.');
    });
});
