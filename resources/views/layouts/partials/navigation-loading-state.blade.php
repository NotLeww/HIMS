<script>
    (function () {
        const storageKey = 'hims:navigation-pending';

        try {
            if (window.sessionStorage.getItem(storageKey) === '1') {
                document.documentElement.classList.add('hims-navigation-pending');
            }
        } catch {
            // Navigation still works when session storage is unavailable.
        }

        window.himsNavigate = window.himsNavigate || function (url, options) {
            const settings = options || {};
            const shouldShowOverlay = settings.showOverlay !== false;

            if (shouldShowOverlay) {
                const overlay = document.querySelector('[data-hims-loading-overlay]');
                const message = overlay?.querySelector('[data-hims-loading-message]');
                if (message) message.textContent = settings.message || 'Loading page...';
                if (overlay) {
                    overlay.hidden = false;
                    overlay.setAttribute('aria-hidden', 'false');
                    document.body.setAttribute('aria-busy', 'true');
                }
            }

            if (settings.replace) {
                window.location.replace(url);
            } else {
                window.location.assign(url);
            }
        };
    })();
</script>
