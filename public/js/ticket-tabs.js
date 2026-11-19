/* SPDX-License-Identifier: GPL-3.0-or-later */
(function () {
    'use strict';
    const match = location.pathname.match(/^(.*)\/front\/ticket\.form\.php$/);
    const id = new URLSearchParams(location.search).get('id');
    if (!match || !/^[1-9]\d*$/.test(id || '') || window.top !== window.self) return;
    const root = match[1];
    function start() {
        const panel = document.getElementById('tabspanel');
        if (!panel || !window.jQuery) return;
        fetch(root + '/plugins/workflowbuilder/front/ticket-tabs.php?id=' + encodeURIComponent(id), {
            credentials: 'same-origin', headers: {Accept: 'application/json'}
        }).then(function (response) {
            if (!response.ok) throw new Error('Tab URLs unavailable');
            return response.json();
        }).then(function (data) {
            const links = Array.from(panel.querySelectorAll('a[data-bs-toggle="tab"]'));
            const byLink = new Map();
            links.forEach(function (link) {
                const ajax = link.getAttribute('data-glpi-ajax-content');
                const key = link.getAttribute('data-show-all-tabs') === 'true' ? '-1'
                    : ajax && new URL(ajax, location.href).searchParams.get('_glpi_tab');
                const tab = data.tabs[key];
                if (!tab) return;
                const url = new URL(tab.url, location.origin);
                // Preserve native options and workflow anchors, but replace obsolete tab parameters.
                new URLSearchParams(location.search).forEach(function (value, name) {
                    if (!['id', 'forcetab', 'tab'].includes(name)) url.searchParams.append(name, value);
                });
                link.href = url.pathname + url.search;
                byLink.set(link, link.href);
            });
            let last = null;
            function sync(link, push) {
                if (!byLink.has(link)) return;
                const url = new URL(byLink.get(link));
                if (last === null || last === link) url.hash = location.hash;
                if (url.href !== location.href) {
                    history[push ? 'pushState' : 'replaceState'](null, '', url);
                }
                last = link;
            }
            const active = panel.querySelector('a.active');
            if (active) sync(active, false);
            window.jQuery(panel).on('shown.bs.tab.wbTicketUrls', 'a[data-bs-toggle="tab"]', function () {
                sync(this, true);
            });
            // Bootstrap catches modifier clicks; preserve normal browser link behavior.
            panel.addEventListener('click', function (event) {
                const link = event.target.closest('a');
                if (byLink.has(link) && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) {
                    event.stopImmediatePropagation();
                }
            }, true);
            // Full reload resolves the URL and restores native GLPI state, including "All".
            window.addEventListener('popstate', function () { location.reload(); });
        }).catch(function () { /* Native GLPI navigation remains available on failure. */ });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}());