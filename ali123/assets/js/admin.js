(function (wp) {
    const { __ } = wp.i18n;

    function request(path, options = {}) {
        options.headers = Object.assign({
            'Content-Type': 'application/json',
            'X-WP-Nonce': Ali123Config.nonce,
        }, options.headers || {});

        return fetch(`${Ali123Config.root}${path}`, options).then((response) => {
            if (!response.ok) {
                return response.json().then((error) => Promise.reject(error));
            }

            if (response.status === 204) {
                return null;
            }

            return response.json ? response.json() : response;
        });
    }

    function renderPlaceholder(id, message) {
        const root = document.getElementById(id);
        if (!root) {
            return;
        }

        root.innerHTML = `<p>${message}</p>`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderPlaceholder('ali123-dashboard-root', __('Ali123 automation is ready. Configure settings to begin importing products.', 'ali123'));

        request('/imports')
            .then((data) => {
                const root = document.getElementById('ali123-import-root');
                if (!root) {
                    return;
                }

                root.innerHTML = `
                    <h2>${__('Queued Imports', 'ali123')}</h2>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>${__('ID', 'ali123')}</th>
                                <th>${__('AliExpress ID', 'ali123')}</th>
                                <th>${__('Status', 'ali123')}</th>
                                <th>${__('Scheduled', 'ali123')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${Object.values(data).map((row) => `
                                <tr>
                                    <td>${row.id}</td>
                                    <td>${row.payload && row.payload.ali_id ? row.payload.ali_id : 'N/A'}</td>
                                    <td>${row.status}</td>
                                    <td>${row.scheduled_at ? new Date(row.scheduled_at.replace(' ', 'T')).toLocaleString() : 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            })
            .catch(() => {
                renderPlaceholder('ali123-import-root', __('Failed to load import queue.', 'ali123'));
            });

        const ordersRoot = document.getElementById('ali123-orders-root');
        if (ordersRoot) {
            const button = document.createElement('button');
            button.className = 'button button-primary';
            button.textContent = __('Sync Tracking Now', 'ali123');
            button.addEventListener('click', () => {
                button.disabled = true;
                button.textContent = __('Scheduling...', 'ali123');
                request('/orders/sync', { method: 'POST' })
                    .then(() => {
                        button.textContent = __('Scheduled', 'ali123');
                    })
                    .catch(() => {
                        button.disabled = false;
                        button.textContent = __('Try Again', 'ali123');
                    });
            });

            ordersRoot.appendChild(button);
        }
    });
})(window.wp || {});
