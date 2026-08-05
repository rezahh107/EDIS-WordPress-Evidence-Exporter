(function () {
    'use strict';

    var config = window.edisDiagnostics || {};
    var output = document.querySelector('[data-edis-worker-output]');
    var canonical = document.querySelector('[data-edis-canonical-json]');
    var idPattern = /^edis-diag-[a-f0-9]{32}$/;
    var codePattern = /^EDIS_[A-Z0-9_]{1,120}$/;

    function own(payload, key) {
        return Object.prototype.hasOwnProperty.call(payload, key);
    }

    function field(payload, snakeKey, camelKey) {
        var hasSnake = own(payload, snakeKey);
        var hasCamel = own(payload, camelKey);
        if (hasSnake && hasCamel && payload[snakeKey] !== payload[camelKey]) {
            return { conflict: true, present: true, value: null };
        }
        return {
            conflict: false,
            present: hasSnake || hasCamel,
            value: hasSnake ? payload[snakeKey] : payload[camelKey]
        };
    }

    function normalizeEnvelope(payload) {
        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
            return null;
        }

        var available = field(payload, 'diagnostic_available', 'diagnosticAvailable');
        var diagnosticId = field(payload, 'diagnostic_id', 'diagnosticId');
        var persistenceCode = field(payload, 'diagnostic_persistence_code', 'diagnosticPersistenceCode');
        var diagnosticsUrl = field(payload, 'diagnostics_url', 'diagnosticsUrl');
        var safeMetadata = field(payload, 'safe_response_metadata', 'safeResponseMetadata');

        if (!available.present || available.conflict || diagnosticId.conflict || persistenceCode.conflict || diagnosticsUrl.conflict || safeMetadata.conflict) {
            return null;
        }
        if (safeMetadata.present && safeMetadata.value !== null && (typeof safeMetadata.value !== 'object' || Array.isArray(safeMetadata.value))) {
            return null;
        }

        return {
            available: available.value,
            idPresent: diagnosticId.present,
            id: diagnosticId.value,
            codePresent: persistenceCode.present,
            code: persistenceCode.value,
            urlPresent: diagnosticsUrl.present,
            url: diagnosticsUrl.value,
            safeMetadata: safeMetadata.present ? safeMetadata.value : null
        };
    }

    function classifyDiagnostic(payload) {
        var envelope = normalizeEnvelope(payload);
        if (!envelope) {
            return null;
        }

        if (envelope.available === true) {
            if (!envelope.idPresent || !idPattern.test(envelope.id || '')) {
                return null;
            }
            if (envelope.codePresent && envelope.code !== null) {
                return null;
            }
            if (envelope.urlPresent && envelope.url !== null && typeof envelope.url !== 'string') {
                return null;
            }
            return {
                state: 'AVAILABLE',
                id: envelope.id,
                url: envelope.url || null,
                code: null,
                safeMetadata: envelope.safeMetadata
            };
        }

        if (envelope.available === false) {
            if (envelope.idPresent && envelope.id !== null) {
                return null;
            }
            if (!envelope.codePresent || !codePattern.test(envelope.code || '')) {
                return null;
            }
            if (envelope.urlPresent && envelope.url !== null) {
                return null;
            }
            return {
                state: envelope.code === 'EDIS_DIAGNOSTIC_CAPACITY_REACHED' ? 'CAPACITY' : 'UNAVAILABLE',
                id: null,
                url: null,
                code: envelope.code,
                safeMetadata: envelope.safeMetadata
            };
        }

        return null;
    }

    function diagnosticData(payload) {
        if (!payload || typeof payload !== 'object') {
            return null;
        }
        return classifyDiagnostic(payload.data && typeof payload.data === 'object' ? payload.data : payload);
    }

    function renderDiagnostic(target, diagnostic) {
        if (!target || !diagnostic) {
            return;
        }
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
            fetch(config.workerTestUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': config.nonce || '' }
            })
                .then(parseResponse)
                .then(function (data) {
                    output.textContent = JSON.stringify(data, null, 2);
                    renderDiagnostic(output, diagnosticData(data));
                })
                .catch(function (error) {
                    output.textContent = error.message;
                    renderDiagnostic(output, diagnosticData(error.payload));
                })
                .finally(function () {
                    worker.disabled = false;
                });
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

    // Public test seam only; canonical copy remains bound to data-edis-copy-canonical.
    // Legacy static contract marker: copy-canonical-diagnostic.
    window.EDISDiagnosticEnvelope = {
        classify: classifyDiagnostic,
        diagnosticData: diagnosticData
    };
}());
