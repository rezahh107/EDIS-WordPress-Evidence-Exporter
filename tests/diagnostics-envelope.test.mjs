import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const diagnosticsSource = fs.readFileSync(new URL('../assets/js/diagnostics.js', import.meta.url), 'utf8');
const adminSources = ['admin.js', 'admin-options.js', 'admin-document-list.js', 'admin-preflight.js', 'admin-jobs.js', 'admin-bind.js'].map((name) => fs.readFileSync(new URL('../assets/js/' + name, import.meta.url), 'utf8'));

function element(tag = 'div') {
  return {
    tag,
    children: [],
    className: '',
    textContent: '',
    innerHTML: '',
    href: '',
    dataset: {},
    classList: { add() {}, remove() {}, toggle() {} },
    appendChild(child) { this.children.push(child); return child; },
    replaceChildren(...children) { this.children = children; this.innerHTML = ''; },
    addEventListener() {},
    querySelector() { return null; },
    querySelectorAll() { return []; },
    scrollIntoView() {},
  };
}

const document = {
  readyState: 'loading',
  querySelector: () => null,
  querySelectorAll: () => [],
  addEventListener: () => {},
  createElement: (tag) => element(tag),
  createTextNode: (value) => ({ textContent: String(value) }),
};
const context = {
  window: {
    location: { href: 'https://example.test/wp-admin/admin.php', origin: 'https://example.test' },
    EDISDiagnosticAdmin: {
      diagnosticsUrl: 'https://example.test/wp-admin/admin.php?page=edis-evidence-diagnostics',
      strings: {
        diagnosticAvailable: 'available',
        diagnosticCapacity: 'capacity',
        diagnosticUnavailable: 'unavailable',
        openDiagnostics: 'open',
      },
    },
    EDISEvidenceAdmin: { restRoot: '/wp-json/edis-evidence-exporter/v3', nonce: 'nonce', strings: { networkError: 'network' } },
  },
  document,
  navigator: { clipboard: { writeText: async () => {} } },
  fetch() { throw new Error('not used'); },
  URL,
  URLSearchParams,
  Map,
  Array,
  Object,
  Number,
  String,
  Boolean,
  JSON,
  Promise,
  setTimeout,
  clearTimeout,
  alert() {},
  prompt() {},
  console,
};
context.window.window = context.window;
context.window.document = document;
vm.createContext(context);
vm.runInContext(diagnosticsSource, context, { filename: 'diagnostics.js' });
for (const [index, source] of adminSources.entries()) { vm.runInContext(source, context, { filename: ['admin.js', 'admin-options.js', 'admin-document-list.js', 'admin-preflight.js', 'admin-jobs.js', 'admin-bind.js'][index] }); }

const shared = context.window.EDISDiagnosticEnvelope;
const consumer = context.window.EDISEvidenceAdminDiagnostics;
assert.equal(typeof shared.classify, 'function');
assert.equal(typeof consumer.renderBoundedError, 'function');

const id = 'edis-diag-' + 'a'.repeat(32);
const available = { code: 'edis_export_failed', data: { status: 500, diagnostic_available: true, diagnostic_id: id, diagnostic_persistence_code: null, diagnostics_url: 'https://example.test/wp-admin/admin.php?page=edis-evidence-diagnostics', safe_response_metadata: { bounded: true } } };
const capacity = { data: { status: 503, diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED', diagnostics_url: null } };
const unavailable = { data: { status: 500, diagnosticAvailable: false, diagnosticId: null, diagnosticPersistenceCode: 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED', diagnosticsUrl: null } };

assert.equal(shared.classify(available).state, 'AVAILABLE');
assert.equal(shared.classify(capacity).state, 'CAPACITY');
assert.equal(shared.classify(unavailable).state, 'UNAVAILABLE');
assert.equal(shared.classify({ data: { diagnostic_available: true, diagnosticAvailable: false, diagnostic_id: id, diagnostic_persistence_code: null } }), null);
assert.equal(shared.classify({ data: { diagnostic_available: true, diagnostic_id: 'bad', diagnostic_persistence_code: null } }), null);
assert.equal(shared.classify({ data: { diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED', diagnostics_url: '/fabricated' } }), null);

function render(payload) {
  const target = element('section');
  consumer.renderBoundedError(target, { message: 'failure', payload }, 'fallback');
  return target;
}

const availableTarget = render(available);
assert.equal(availableTarget.children.length, 1);
const availableNotice = availableTarget.children[0];
assert.equal(availableNotice.children[0].textContent, 'available');
assert.equal(availableNotice.children[1].children[0].textContent, id);
assert.match(availableNotice.children[2].href, new RegExp(`diagnostic_id=${id}$`));

const capacityTarget = render(capacity);
assert.equal(capacityTarget.children[0].children[0].textContent, 'capacity');
assert.equal(capacityTarget.children[0].children[1].children[0].textContent, 'EDIS_DIAGNOSTIC_CAPACITY_REACHED');
assert.equal(capacityTarget.children[0].children.some((child) => child.tag === 'a'), false);

const unavailableTarget = render(unavailable);
assert.equal(unavailableTarget.children[0].children[0].textContent, 'unavailable');
assert.equal(unavailableTarget.children[0].children[1].children[0].textContent, 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED');

const malformedTarget = render({ data: { diagnostic_available: true, diagnostic_id: 'bad', diagnostic_persistence_code: null } });
assert.equal(malformedTarget.children.length, 0);
assert.match(malformedTarget.innerHTML, /failure/);

const networkTarget = element('section');
consumer.renderBoundedError(networkTarget, new Error('offline'), 'fallback');
assert.equal(networkTarget.children.length, 0);
assert.match(networkTarget.innerHTML, /offline/);

console.log(JSON.stringify({ state: 'PASS', classifier: true, productionConsumer: true }));
