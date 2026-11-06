/**
 * SPLASMA Scan Result — presentation-only universal scan feedback.
 * Tidak mengubah aturan bisnis atau response API.
 */
(function (window) {
    'use strict';

    function esc(value) {
        return String(value ?? '').replace(/[&<>\'"]/g, function (c) {
            return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' })[c];
        });
    }

    function render(el, state, data) {
        if (!el) return;
        data = data || {};

        var map = {
            success: { cls: 'success', icon: 'bi-check-circle-fill', title: data.title || 'SCAN BERHASIL' },
            duplicate: { cls: 'duplicate', icon: 'bi-exclamation-circle-fill', title: data.title || 'SUDAH TERCATAT' },
            rejected: { cls: 'rejected', icon: 'bi-exclamation-triangle-fill', title: data.title || 'SCAN DITOLAK' },
            error: { cls: 'error', icon: 'bi-x-circle-fill', title: data.title || 'SCAN GAGAL' },
            neutral: { cls: 'neutral', icon: 'bi-qr-code-scan', title: data.title || 'SIAP SCAN' }
        };
        var meta = map[state] || map.error;
        var detail = data.detail || '';
        var name = data.name || '';
        var time = data.time || '';

        el.className = 'scan-result splasma-scan-result ' + meta.cls;
        el.setAttribute('role', state === 'error' || state === 'rejected' ? 'alert' : 'status');
        el.setAttribute('aria-live', 'assertive');
        el.innerHTML = '<div class="scan-result-icon"><i class="bi ' + meta.icon + '"></i></div>' +
            '<div class="scan-result-body">' +
                '<div class="scan-result-title">' + esc(meta.title) + '</div>' +
                (name ? '<div class="scan-result-name">' + esc(name) + '</div>' : '') +
                (detail ? '<div class="scan-result-detail">' + detail + '</div>' : '') +
                (time ? '<div class="scan-result-time">' + esc(time) + '</div>' : '') +
            '</div>';
    }

    function fromResponse(el, response, fallback) {
        response = response || {};
        fallback = fallback || {};
        if (response.ok) {
            render(el, 'success', {
                title: fallback.successTitle || 'SCAN BERHASIL',
                name: response.nama || response.student?.nama || fallback.name || '',
                detail: fallback.successDetail || '',
                time: response.waktu || fallback.time || ''
            });
            return 'success';
        }
        if (response.duplicate) {
            render(el, 'duplicate', {
                title: fallback.duplicateTitle || 'SUDAH TERCATAT',
                name: response.nama || response.student?.nama || fallback.name || '',
                detail: fallback.duplicateDetail || esc(response.message || 'Data sudah tercatat.'),
                time: response.waktu || ''
            });
            return 'duplicate';
        }
        render(el, 'rejected', {
            title: fallback.rejectedTitle || 'SCAN DITOLAK',
            name: response.nama || response.student?.nama || '',
            detail: esc(response.message || fallback.message || 'Transaksi tidak dapat diproses.'),
            time: response.waktu || ''
        });
        return 'rejected';
    }

    window.SPLASMA_SCAN_RESULT = { render: render, fromResponse: fromResponse };
})(window);
