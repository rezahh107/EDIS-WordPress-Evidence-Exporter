import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const diagnosticsSource = fs.readFileSync(new URL('../assets/js/diagnostics.js', import.meta.url), 'utf8');
const adminSources = ['admin.js', 'admin-options.js', 'admin-document-list.js', 'admin-preflight.js', 'admin-jobs.js', 'admin-bind.js'].map((name) => fs.readFileSync(new URL('../assets/js/' + name, import.meta.url), 'utf8'));

function element(tag = 'div') {
  let innerHTML = '';
  const listeners = {};
  const node = {
    tag,
    children: [],
    className: '',
    textContent: '',
    href: '',
    dataset: {},
    classList: { add() {}, remove() {}, toggle() {} },
    appendChild(child) { this.children.push(child); return child; },
    replaceChildren(...children) { this.children = children; innerHTML = ''; },
    addEventListener(type, handler) { listeners[type] = handler; },
    async click() { return listeners.click?.({ preventDefault() {} }); },
    querySelector() { return null; },
    querySelectorAll() { return []; },
    scrollIntoView() {},
  };
  Object.defineProperty(node, 'innerHTML', {
    get() { return innerHTML; },
    set(value) { innerHTML = String(value); node.children = []; },
  });
  return node;
}

const workerButton = element('button');
const workerOutput = element('section');
const nodes = new Map([
  ['[data-edis-action="worker-test"]', workerButton],
  ['#edis-worker-test-result', workerOutput],
]);
let workerPayload = null;
const document = {
  readyState: 'complete',
  querySelector: (selector) => nodes.get(selector) || null,
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
  fetch: async () => ({ ok: true, status: 200, json: async () => workerPayload }),
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

async function runWorkerTest(payload) {
  workerPayload = payload;
  workerOutput.replaceChildren();
  workerOutput.innerHTML = '';
  await workerButton.click();
  return workerOutput;
}

function assertRawWorkerResult(target, state) {
  assert.equal(target.children.length, 0);
  assert.match(target.innerHTML, new RegExp(`&quot;state&quot;: &quot;${state}&quot;`));
  assert.doesNotMatch(target.innerHTML, /edis-diagnostic-envelope|diagnostic_id=/);
}

const workerAvailable = await runWorkerTest({
  state: 'FAIL',
  diagnostic_available: true,
  diagnostic_id: id,
  diagnostic_persistence_code: null,
  diagnostics_url: 'https://example.test/wp-admin/admin.php?page=edis-evidence-diagnostics',
});
assert.equal(workerAvailable.children.length, 2);
assert.equal(workerAvailable.children[0].children[0].textContent, 'available');
assert.equal(workerAvailable.children[0].children[1].children[0].textContent, id);
assert.match(workerAvailable.children[0].children[2].href, new RegExp(`diagnostic_id=${id}$`));
assert.match(workerAvailable.children[1].textContent, /"state": "FAIL"/);

const workerCapacity = await runWorkerTest({
  state: 'FAIL',
  diagnostic_available: false,
  diagnostic_id: null,
  diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED',
  diagnostics_url: null,
});
assert.equal(workerCapacity.children.length, 2);
assert.equal(workerCapacity.children[0].children[0].textContent, 'capacity');
assert.equal(workerCapacity.children[0].children[1].children[0].textContent, 'EDIS_DIAGNOSTIC_CAPACITY_REACHED');
assert.equal(workerCapacity.children[0].children.some((child) => child.tag === 'a'), false);
assert.match(workerCapacity.children[1].textContent, /"state": "FAIL"/);

const workerUnavailable = await runWorkerTest({
  state: 'FAIL',
  diagnostic_available: false,
  diagnostic_id: null,
  diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED',
  diagnostics_url: null,
});
assert.equal(workerUnavailable.children.length, 2);
assert.equal(workerUnavailable.children[0].children[0].textContent, 'unavailable');
assert.equal(workerUnavailable.children[0].children[1].children[0].textContent, 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED');
assert.match(workerUnavailable.children[1].textContent, /"state": "FAIL"/);

const malformedWorkerPayloads = [
  { state: 'FAIL', diagnostic_available: true, diagnostic_id: 'bad', diagnostic_persistence_code: null, diagnostics_url: null },
  { state: 'FAIL', diagnostic_available: true, diagnosticAvailable: false, diagnostic_id: id, diagnostic_persistence_code: null, diagnostics_url: null },
  { state: 'FAIL', diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED', diagnostics_url: '/fabricated' },
  { state: 'FAIL', diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'invalid code!', diagnostics_url: null },
  { state: 'FAIL', message: 'bounded worker failure' },
];
for (const payload of malformedWorkerPayloads) {
  assertRawWorkerResult(await runWorkerTest(payload), 'FAIL');
}

for (const state of ['PASS', 'IN_PROGRESS', 'ABORTED', 'UNKNOWN']) {
  assertRawWorkerResult(await runWorkerTest({ state, message: `worker ${state}` }), state);
}

console.log(JSON.stringify({ state: 'PASS', classifier: true, productionConsumer: true, fulfilledWorkerTest: true }));
