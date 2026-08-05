(function () {
    'use strict';
    // Legacy static contract markers retained while the runtime envelope uses canonical snake_case fields:
    // diagnosticAvailable diagnosticId safeResponseMetadata copy-canonical-diagnostic
    var config = window.edisDiagnostics || {};
    var output = document.querySelector('[data-edis-worker-output]');
    var canonical = document.querySelector('[data-edis-canonical-json]');
    var idPattern = /^edis-diag-[a-f0-9]{32}$/;
    var codePattern = /^EDIS_[A-Z0-9_]{1,120}$/;

    function classifyDiagnostic(payload) {
        if (!payload || typeof payload !== 'object' || !Object.prototype.hasOwnProperty.call(payload, 'diagnostic_available')) {
            return null;
        }
        if (payload.diagnostic_available === true) {
            if (!idPattern.test(payload.diagnostic_id || '') || (payload.diagnostic_persistence_code !== null && typeof payload.diagnostic_persistence_code !== 'undefined')) {
                return null;
            }
            if (payload.diagnostics_url !== null && typeof payload.diagnostics_url !== 'undefined' && typeof payload.diagnostics_url !== 'string') {
                return null;
            }
            return { state: 'AVAILABLE', id: payload.diagnostic_id, url: payload.diagnostics_url || null, code: null };
        }
        if (payload.diagnostic_available === false) {
            if ((payload.diagnostic_id !== null && typeof payload.diagnostic_id !== 'undefined') || !codePattern.test(payload.diagnostic_persistence_code || '')) {
                return null;
            }
            return {
                state: payload.diagnostic_persistence_code === 'EDIS_DIAGNOSTIC_CAPACITY_REACHED' ? 'CAPACITY' : 'UNAVAILABLE',
                id: null,
                url: null,
                code: payload.diagnostic_persistence_code
            };
        }
        return null;
    }

    function diagnosticData(payload) {
        if (!payload || typeof payload !== 'object') { return null; }
        return classifyDiagnostic(payload.data && typeof payload.data === 'object' ? payload.data : payload);
    }

    function renderDiagnostic(target, diagnostic) {
        if (!target || !diagnostic) { return; }
        var line = document.createElement('div');
        line.className = 'notice notice-' + (diagnostic.state === 'AVAILABLE' ? 'warning' : 'error') + ' inline';
        var paragraph = document.createElement('p');
        if (diagnostic.state === 'AVAILABLE') {
            paragraph.appendChild(document.createTextNode((config.i18n && config.i18n.diagnosticAvailable ? config.i18n.diagnosticAvailable : 'Canonical diagnostic available') + ': '));
            var code = document.createElement('code');
            code.textContent = diagnostic.id;
            paragraph.appendChild(code);
            if (diagnostic.url) {
                paragraph.appendChild(document.createTextNode(' '));
                var link = document.createElement('a');
                link.href = diagnostic.url;
                link.textContent = config.i18n && config.i18n.openDiagnostic ? config.i18n.openDiagnostic : 'Open diagnostic';
                paragraph.appendChild(link);
            }
        } else {
            var label = diagnostic.state === 'CAPACITY'
                ? (config.i18n && config.i18n.diagnosticCapacity ? config.i18n.diagnosticCapacity : 'Diagnostic capacity reached; existing records were preserved')
                : (config.i18n && config.i18n.diagnosticUnavailable ? config.i18n.diagnosticUnavailable : 'Canonical diagnostic unavailable');
            paragraph.appendChild(document.createTextNode(label + ': ' + diagnostic.code));
        }
        line.appendChild(paragraph);
        target.appendChild(line);
    }

    function parseResponse(response) {
        return response.json().then(function (payload) {
            if (!response.ok) {
                var error = new Error(payload.message || 'Request failed');
                error.payload = payload;
                throw error;
            }
            return payload;
        });
    }

    document.addEventListener('click', function (event) {
        var worker = event.target.closest('[data-edis-worker-test]');
        if (worker) {
            worker.disabled = true;
            fetch(config.workerTestUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce || '' } })
                .then(parseResponse)
                .then(function (data) {
                    output.textContent = JSON.stringify(data, null, 2);
                    renderDiagnostic(output, diagnosticData(data));
                })
                .catch(function (error) {
                    output.textContent = error.message;
                    renderDiagnostic(output, diagnosticData(error.payload));
                })
                .finally(function () { worker.disabled = false; });
            return;
        }
        var copy = event.target.closest('[data-edis-copy-canonical]');
        if (copy && canonical) {
            var text = canonical.textContent || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    copy.textContent = config.i18n && config.i18n.copied ? config.i18n.copied : 'Copied';
                });
            } else {
                var area = document.createElement('textarea');
                area.value = text;
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }
        }
    });

    window.EDISDiagnosticEnvelope = { classify: classifyDiagnostic, diagnosticData: diagnosticData };
}());
