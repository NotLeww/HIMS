import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Dashboard live updates via 30s polling.
 *
 * Swaps in fresh alert HTML and updates the stat tiles so the dashboard
 * reflects stock recorded from another screen without a manual reload.
 * Pauses when the tab is hidden and refreshes on tab focus.
 */
Alpine.data('dashboardLive', (endpoint) => ({
    intervalId: null,
    statusLabel: '',

    start() {
        this.poll();
        this.intervalId = setInterval(() => this.poll(), 30000);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pause();
            } else {
                this.resume();
            }
        });
    },

    pause() {
        if (this.intervalId !== null) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    },

    resume() {
        if (this.intervalId === null) {
            this.poll();
            this.intervalId = setInterval(() => this.poll(), 30000);
        }
    },

    refresh() {
        this.poll();
    },

    async poll() {
        try {
            const response = await fetch(endpoint);
            if (!response.ok) return;

            const data = await response.json();

            this.$refs.alerts.innerHTML = data.alertsHtml;

            // Update stat tiles
            const lowStockTile = this.$refs.lowStockTile;
            const openAlertTile = this.$refs.openAlertTile;

            if (lowStockTile) {
                const valueEl = lowStockTile.querySelector('[data-stat-value]');
                const hintEl = lowStockTile.querySelector('[data-stat-hint]');
                if (valueEl) valueEl.textContent = new Intl.NumberFormat().format(data.lowStockItems);
                if (hintEl) {
                    hintEl.textContent = data.outOfStockItems > 0
                        ? `${new Intl.NumberFormat().format(data.outOfStockItems)} fully out of stock`
                        : 'No items out of stock';
                }
            }

            if (openAlertTile) {
                const valueEl = openAlertTile.querySelector('[data-stat-value]');
                const hintEl = openAlertTile.querySelector('[data-stat-hint]');
                if (valueEl) valueEl.textContent = new Intl.NumberFormat().format(data.openAlertCount);
                if (hintEl) {
                    hintEl.textContent = data.openAlertCount > 0
                        ? 'Awaiting acknowledgement'
                        : 'Nothing outstanding';
                }
            }

            const now = new Date();
            this.statusLabel = `— refreshed ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
        } catch (error) {
            console.error('Dashboard poll failed:', error);
        }
    }
}));

Alpine.start();
