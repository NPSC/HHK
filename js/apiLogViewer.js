/**
 * apiLogViewer.js
 *
 * Shared viewer for the external_api_log table (Guzzle request/response log).
 * Used by the Payment log (admin/PaymentTx.php) and the CRM transfer log
 * (house/GuestTransfer.php).
 *
 * Usage:
 *   HhkApiLog.init('#deluxeLog', { url: 'ws_gen.php', service: () => 'deluxe' });
 *
 * Detail rows break the request down into query parameters, headers and body,
 * and format bodies by content (JSON, form-urlencoded, XML, plain text).
 * Everything coming from the log is HTML-escaped before rendering.
 */
(function (window, $) {
    'use strict';

    const STYLE_ID = 'hhk-apilog-style';
    const CSS = `
        .hhk-apilog { display: flex; flex-wrap: wrap; gap: 1rem; padding: .5rem; text-align: left; }
        .hhk-apilog-panel { flex: 1 1 28rem; min-width: 0; padding: .5rem .75rem; }
        .hhk-apilog-panel h4 { margin: 0 0 .5rem; font-size: 1.1em; display: flex; align-items: center; gap: .5rem; }
        .hhk-apilog-url { font-family: monospace; word-break: break-all; margin-bottom: .5rem; }
        .hhk-apilog-url .hhk-apilog-path { font-weight: bold; }
        .hhk-apilog details { margin: .4rem 0; }
        .hhk-apilog summary { cursor: pointer; font-weight: bold; }
        .hhk-apilog summary .hhk-apilog-count { font-weight: normal; opacity: .7; }
        .hhk-apilog pre { white-space: pre-wrap; word-break: break-word; margin: .25rem 0; max-height: 40em; overflow: auto; }
        .hhk-apilog-kv { border-collapse: collapse; width: 100%; margin: .25rem 0; font-size: .95em; }
        .hhk-apilog-kv td { border: 1px solid rgba(0,0,0,.12); padding: .15rem .4rem; vertical-align: top; text-align: left; }
        .hhk-apilog-kv td:first-child { font-family: monospace; white-space: nowrap; width: 1%; font-weight: bold; }
        .hhk-apilog-kv td:last-child { font-family: monospace; white-space: pre-wrap; word-break: break-word; }
        .hhk-apilog-kv tr:nth-child(even) td { background: rgba(0,0,0,.03); }
        .hhk-apilog-badge { display: inline-block; padding: 0 .4em; border-radius: .25em; font-size: .85em; font-weight: bold; background: rgba(0,0,0,.08); }
        .hhk-apilog-badge.ok { background: #d4edda; color: #155724; }
        .hhk-apilog-badge.err { background: #f8d7da; color: #721c24; }
        .hhk-apilog-fmt { font-size: .8em; font-weight: normal; opacity: .7; }
        .hhk-apilog-endpoint { font-family: monospace; word-break: break-all; }
    `;

    function injectStyle() {
        if (!document.getElementById(STYLE_ID)) {
            const style = document.createElement('style');
            style.id = STYLE_ID;
            style.textContent = CSS;
            document.head.appendChild(style);
        }
    }

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function safeDecode(s) {
        try {
            return decodeURIComponent(s.replace(/\+/g, ' '));
        } catch (e) {
            return s;
        }
    }

    /** Split a URL into base and decoded [key, value] query pairs, without needing an absolute URL. */
    function parseUrl(url) {
        const str = String(url ?? '');
        const hashless = str.split('#')[0];
        const qPos = hashless.indexOf('?');
        if (qPos === -1) {
            return { base: hashless, params: [] };
        }
        return { base: hashless.substring(0, qPos), params: parseFormEncoded(hashless.substring(qPos + 1)) };
    }

    function parseFormEncoded(str) {
        return String(str).split('&').filter(p => p !== '').map(pair => {
            const eq = pair.indexOf('=');
            return eq === -1
                ? [safeDecode(pair), '']
                : [safeDecode(pair.substring(0, eq)), safeDecode(pair.substring(eq + 1))];
        });
    }

    function looksFormEncoded(str) {
        return /^[^=&\s]+=[^&\s]*(&[^=&\s]+=[^&\s]*)*$/.test(str);
    }

    function tryJson(str) {
        const t = str.trim();
        if (!(t.startsWith('{') || t.startsWith('['))) {
            return undefined;
        }
        try {
            return JSON.parse(t);
        } catch (e) {
            return undefined;
        }
    }

    function prettyXml(str) {
        let doc;
        try {
            doc = new DOMParser().parseFromString(str.trim(), 'application/xml');
        } catch (e) {
            return null;
        }
        if (!doc || doc.getElementsByTagName('parsererror').length) {
            return null;
        }

        const lines = [];
        const walk = (node, depth) => {
            const pad = '  '.repeat(depth);
            if (node.nodeType === Node.TEXT_NODE || node.nodeType === Node.CDATA_SECTION_NODE) {
                const t = node.nodeValue.trim();
                if (t) { lines.push(pad + t); }
                return;
            }
            if (node.nodeType === Node.COMMENT_NODE) {
                lines.push(pad + '<!--' + node.nodeValue + '-->');
                return;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }
            const attrs = Array.from(node.attributes).map(a => ` ${a.name}="${a.value}"`).join('');
            const children = Array.from(node.childNodes).filter(c =>
                c.nodeType !== Node.TEXT_NODE || c.nodeValue.trim() !== '');

            if (children.length === 0) {
                lines.push(`${pad}<${node.nodeName}${attrs}/>`);
            } else if (children.length === 1 && children[0].nodeType === Node.TEXT_NODE) {
                lines.push(`${pad}<${node.nodeName}${attrs}>${children[0].nodeValue.trim()}</${node.nodeName}>`);
            } else {
                lines.push(`${pad}<${node.nodeName}${attrs}>`);
                children.forEach(c => walk(c, depth + 1));
                lines.push(`${pad}</${node.nodeName}>`);
            }
        };
        Array.from(doc.childNodes).forEach(n => walk(n, 0));
        return lines.join('\n');
    }

    function kvTable(pairs) {
        return '<table class="hhk-apilog-kv"><tbody>' +
            pairs.map(([k, v]) => {
                const json = typeof v === 'string' ? tryJson(v) : undefined;
                const shown = json !== undefined ? JSON.stringify(json, null, 2) : v;
                return `<tr><td>${esc(k)}</td><td>${esc(shown)}</td></tr>`;
            }).join('') +
            '</tbody></table>';
    }

    function section(title, count, inner, open) {
        const countHtml = count !== null ? ` <span class="hhk-apilog-count">(${count})</span>` : '';
        return `<details${open ? ' open' : ''}><summary>${esc(title)}${countHtml}</summary>${inner}</details>`;
    }

    /**
     * Format a body string by content type. Returns { html, format, formatted }.
     * formatted=false means the raw text is shown as-is (no separate "raw" view needed).
     */
    function formatBody(body) {
        const str = String(body ?? '');
        if (str.trim() === '') {
            return { html: '<em>(empty)</em>', format: '', formatted: false };
        }

        const json = tryJson(str);
        if (json !== undefined) {
            if (json !== null && typeof json === 'object' && !Array.isArray(json)
                && Object.values(json).every(v => v === null || typeof v !== 'object')) {
                // flat object: a key/value table is easier to scan than JSON
                return { html: kvTable(Object.entries(json).map(([k, v]) => [k, v === null ? 'null' : String(v)])), format: 'JSON', formatted: true };
            }
            return { html: `<pre>${esc(JSON.stringify(json, null, 2))}</pre>`, format: 'JSON', formatted: str.trim() !== JSON.stringify(json, null, 2) };
        }

        const trimmed = str.trim();
        if (looksFormEncoded(trimmed)) {
            const pairs = parseFormEncoded(trimmed);
            return { html: kvTable(pairs), format: 'Form', formatted: true };
        }

        if (trimmed.startsWith('<')) {
            const xml = prettyXml(trimmed);
            if (xml !== null) {
                return { html: `<pre>${esc(xml)}</pre>`, format: 'XML', formatted: xml !== trimmed };
            }
        }

        return { html: `<pre>${esc(str)}</pre>`, format: '', formatted: false };
    }

    function bodySection(title, body, open) {
        const f = formatBody(body);
        const fmt = f.format ? ` <span class="hhk-apilog-fmt">${esc(f.format)}</span>` : '';
        let inner = f.html;
        if (f.formatted) {
            inner += section('Raw', null, `<pre>${esc(body)}</pre>`, false);
        }
        return `<details${open ? ' open' : ''}><summary>${esc(title)}${fmt}</summary>${inner}</details>`;
    }

    function headerPairs(headers) {
        const parsed = tryJson(String(headers ?? ''));
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            return Object.entries(parsed).map(([k, v]) => [k, Array.isArray(v) ? v.join(', ') : String(v)]);
        }
        return null;
    }

    function statusBadge(code) {
        const n = parseInt(code, 10);
        const cls = isNaN(n) ? '' : (n >= 400 ? 'err' : (n < 300 ? 'ok' : ''));
        return `<span class="hhk-apilog-badge ${cls}">${esc(code)}</span>`;
    }

    function formatDetails(row) {
        const { base, params } = parseUrl(row.endpoint);

        let req = `<div class="hhk-apilog-panel ui-widget ui-widget-content ui-corner-all">
            <h4>Request <span class="hhk-apilog-badge">${esc(row.requestMethod)}</span></h4>
            <div class="hhk-apilog-url"><span class="hhk-apilog-path">${esc(base)}</span></div>`;

        if (params.length) {
            req += section('Query Parameters', params.length, kvTable(params), true);
            req += section('Full URL', null, `<pre>${esc(row.endpoint)}</pre>`, false);
        }

        const hp = headerPairs(row.requestHeaders);
        if (hp && hp.length) {
            req += section('Headers', hp.length, kvTable(hp), false);
        } else if (row.requestHeaders) {
            req += section('Headers', null, `<pre>${esc(row.requestHeaders)}</pre>`, false);
        }

        if (row.request) {
            req += bodySection('Body', row.request, true);
        }
        req += '</div>';

        const resp = `<div class="hhk-apilog-panel ui-widget ui-widget-content ui-corner-all">
            <h4>Response ${statusBadge(row.responseCode)}</h4>
            ${bodySection('Body', row.response, true)}
        </div>`;

        return `<div class="hhk-apilog">${req}${resp}</div>`;
    }

    function renderEndpoint(data, type) {
        if (type !== 'display') {
            return data;
        }
        const { base, params } = parseUrl(data);
        // drop scheme+host to keep the column narrow; full URL is in the tooltip and detail row
        const path = base.replace(/^[a-z]+:\/\/[^/]+/i, '') || base;
        const badge = params.length
            ? ` <span class="hhk-apilog-badge" title="${params.length} query parameter${params.length > 1 ? 's' : ''}">?${params.length}</span>`
            : '';
        return `<span class="hhk-apilog-endpoint" title="${esc(data)}">${esc(path)}</span>${badge}`;
    }

    function columns() {
        return [
            { targets: [0], className: 'dt-control', orderable: false, data: null, defaultContent: '' },
            { targets: [1], title: 'Method', searchable: false, sortable: true, data: 'requestMethod', render: $.fn.dataTable.render.text() },
            { targets: [2], title: 'Type', searchable: false, sortable: true, data: 'Type', render: $.fn.dataTable.render.text() },
            { targets: [3], title: 'Request Endpoint', searchable: false, sortable: false, data: 'endpoint', render: renderEndpoint },
            { targets: [4], title: 'Response Code', searchable: false, sortable: true, data: 'responseCode', render: (d, t) => t === 'display' ? statusBadge(d) : d },
            { targets: [5], title: 'Request', searchable: true, sortable: true, data: 'request', visible: false },
            { targets: [6], title: 'Response', searchable: true, sortable: true, data: 'response', visible: false },
            { targets: [7], title: 'User', searchable: true, sortable: true, data: 'username', render: $.fn.dataTable.render.text() },
            {
                targets: [8], title: 'Timestamp', data: 'Timestamp',
                render: (data, type) => dateRender(data, type, 'MMM D YYYY h:mm:ss a')
            }
        ];
    }

    /**
     * Build the log DataTable and wire up the expandable detail rows.
     * @param {string} selector  table selector
     * @param {{url: string, service: function(): string}} opts
     * @returns jQuery dataTable object
     */
    function init(selector, opts) {
        injectStyle();

        const $table = $(selector);
        const logTable = $table.dataTable({
            columnDefs: columns(),
            serverSide: true,
            processing: true,
            language: { sSearch: 'Search Log:' },
            sorting: [[8, 'desc']],
            displayLength: 25,
            lengthMenu: [[25, 50, 100], [25, 50, 100]],
            dom: '<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom"lp><"clear">',
            autoWidth: false,
            layout: {
                topStart: 'info',
                bottom: 'paging',
                bottomStart: null,
                bottomEnd: null
            },
            ajax: {
                url: opts.url,
                data: function (d) {
                    d.cmd = 'viewLog';
                    d.service = opts.service();
                }
            },
            createdRow: function (row, data) {
                if (data.responseCode >= 400) {
                    $(row).addClass('ui-state-error');
                }
            }
        });

        $table.on('click', 'td.dt-control', function (e) {
            const row = logTable.DataTable().row(e.target.closest('tr'));
            if (row.child.isShown()) {
                row.child.hide();
            } else {
                row.child(formatDetails(row.data())).show();
            }
        });

        return logTable;
    }

    window.HhkApiLog = { init, formatDetails, formatBody, parseUrl };

})(window, jQuery);
