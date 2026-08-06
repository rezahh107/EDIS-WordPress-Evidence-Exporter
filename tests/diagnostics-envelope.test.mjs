import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const diagnosticsSource = fs.readFileSync(new URL('../assets/js/diagnostics.js', import.meta.url), 'utf8');
const adminNames = ['admin.js', 'admin-options.js', 'admin-document-list.js', 'admin-preflight.js', 'admin-jobs.js', 'admin-bind.js'];
const adminSources = adminNames.map((name) => fs.readFileSync(new URL('../assets/js/' + name, import.meta.url), 'utf8'));
const inspectorSource = fs.readFileSync(new URL('../assets/js/elementor-inspector.js', import.meta.url), 'utf8');

function classList() {
  const values = new Set();
  return {
    add(...items) { items.forEach((item) => values.add(item)); },
    remove(...items) { items.forEach((item) => values.delete(item)); },
    toggle(item, force) {
      if (force === true) values.add(item);
      else if (force === false) values.delete(item);
      else if (values.has(item)) values.delete(item);
      else values.add(item);
      return values.has(item);
    },
    contains(item) { return values.has(item); },
  };
}

function element(tag = 'div') {
  let innerHTML = '';
  const listeners = {};
  const queries = new Map();
  const node = {
    tag,
    id: '',
    hidden: false,
    children: [],
    className: '',
    textContent: '',
    href: '',
    dataset: {},
    classList: classList(),
    appendChild(child) { this.children.push(child); return child; },
    replaceChildren(...children) { this.children = children; innerHTML = ''; },
    addEventListener(type, handler) { listeners[type] = handler; },
    async click() { return listeners.click?.({ preventDefault() {} }); },
    querySelector(selector) { return queries.get(selector) || null; },
    querySelectorAll(selector) {
      if (selector === '[data-edis-remove]') return [];
      return [];
    },
    scrollIntoView() {},
    setAttribute() {},
    _queries: queries,
  };
  Object.defineProperty(node, 'innerHTML', {
    get() { return innerHTML; },
    set(value) {
      innerHTML = String(value);
      node.children = [];
      if (innerHTML.includes('data-edis-count') && innerHTML.includes('data-edis-status')) {
        const count = element('strong');
        const items = element('ul');
        const status = element('p');
        const open = element('button');
        const clear = element('button');
        queries.set('[data-edis-count]', count);
        queries.set('[data-edis-items]', items);
        queries.set('[data-edis-status]', status);
        queries.set('[data-edis-open]', open);
        queries.set('[data-edis-clear]', clear);
      }
    },
  });
  return node;
}

const workerButton = element('button');
const workerOutput = element('section');
const selectorNodes = new Map([
  ['[data-edis-action="worker-test"]', workerButton],
  ['#edis-worker-test-result', workerOutput],
]);
const idNodes = new Map();
const eventListeners = new Map();
const hookFilters = new Map();
let workerPayload = null;
let inspectorPayload = null;
let openedUrl = null;

const document = {
  readyState: 'complete',
  body: {
    appendChild(node) {
      if (node.id) idNodes.set(node.id, node);
      return node;
    },
  },
  querySelector: (selector) => selectorNodes.get(selector) || null,
  querySelectorAll: () => [],
  getElementById: (id) => idNodes.get(id) || null,
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
    EDISElementorInspector: {
      selectionEndpoint: 'https://example.test/wp-json/edis-evidence-exporter/v3/inspector-selections',
      restNonce: 'nonce',
      maxSelections: 50,
      strings: {
        groupTitle: 'EDIS Inspector',
        exportElement: 'Export element',
        exportSubtree: 'Export subtree',
        addSelection: 'Add selection',
        removeSelection: 'Remove selection',
        openSelection: 'Open selection',
        clearSelection: 'Clear',
        selectionCount: 'Selection: %d',
        requestFailed: 'request failed',
        removeItem: 'Remove',
      },
    },
    elementor: {
      documents: { currentDocument: { id: 42 } },
      hooks: {
        addFilter(name, handler) { hookFilters.set(name, handler); },
      },
      saver: { isEditorChanged: () => false },
    },
    addEventListener(name, handler) { eventListeners.set(name, handler); },
    open(url) { openedUrl = url; },
  },
  document,
  navigator: { clipboard: { writeText: async () => {} } },
  fetch: async (url) => {
    if (String(url).includes('inspector-selections')) {
      return { ok: false, status: 500, json: async () => inspectorPayload };
    }
    return { ok: true, status: 200, json: async () => workerPayload };
  },
  URL,
  URLSearchParams,
  Map,
  Set,
  Array,
  Object,
  Number,
  String,
  Boolean,
  JSON,
  Promise,
  RegExp,
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
for (const [index, source] of adminSources.entries()) {
  vm.runInContext(source, context, { filename: adminNames[index] });
}

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
assert.equal(shared.classify({ data: { diagnostic_available: true, diagnostic_id: 'edis-' + 'a'.repeat(32), diagnostic_persistence_code: null } }), null);
assert.equal(shared.classify({ data: { diagnostic_available: true, diagnostic_id: id, diagnostic_persistence_code: null, diagnostics_url: 'https://attacker.test/diagnostics' } }), null);
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
  { state: 'FAIL', diagnostic_available: true, diagnostic_id: id, diagnostic_persistence_code: null, diagnostics_url: 'https://attacker.test/diagnostics' },
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

vm.runInContext(inspectorSource, context, { filename: 'elementor-inspector.js' });
assert.equal(typeof eventListeners.get('elementor/init'), 'function');
eventListeners.get('elementor/init')();
const widgetHook = hookFilters.get('elements/widget/contextMenuGroups');
assert.equal(typeof widgetHook, 'function');
const view = {
  model: {
    get(name) {
      return ({ id: 'abc_123', elType: 'widget', widgetType: 'heading', title: 'Heading' })[name];
    },
  },
};
const groups = widgetHook([], view);
assert.equal(groups.length, 1);
assert.equal(groups[0].name, 'edis-evidence-inspector');
const exportAction = groups[0].actions[0];
assert.equal(exportAction.isEnabled(), true);

function inspectorStatus() {
  const tray = idNodes.get('edis-inspector-tray');
  assert.ok(tray);
  const status = tray.querySelector('[data-edis-status]');
  assert.ok(status);
  status.replaceChildren();
  status.textContent = '';
  return status;
}

async function runInspector(payload) {
  inspectorPayload = payload;
  openedUrl = null;
  const status = inspectorStatus();
  await exportAction.callback();
  return { status, openedUrl };
}

const inspectorAvailable = await runInspector({
  code: 'edis_inspector_selection_failed',
  message: 'Inspector failed.',
  data: {
    status: 500,
    diagnostic_available: true,
    diagnostic_id: id,
    diagnostic_persistence_code: null,
    diagnostics_url: 'https://example.test/wp-admin/admin.php?page=edis-evidence-diagnostics',
  },
});
assert.equal(inspectorAvailable.openedUrl, null);
assert.equal(inspectorAvailable.status.children.length, 1);
assert.equal(inspectorAvailable.status.children[0].children[0].textContent, 'available');
assert.equal(inspectorAvailable.status.children[0].children[1].children[0].textContent, id);
assert.match(inspectorAvailable.status.children[0].children[2].href, new RegExp(`diagnostic_id=${id}$`));

const inspectorCapacity = await runInspector({
  code: 'edis_inspector_selection_failed',
  message: 'Inspector failed.',
  data: {
    status: 500,
    diagnostic_available: false,
    diagnostic_id: null,
    diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED',
    diagnostics_url: null,
  },
});
assert.equal(inspectorCapacity.status.children[0].children[0].textContent, 'capacity');
assert.equal(inspectorCapacity.status.children[0].children.some((child) => child.tag === 'a'), false);

const inspectorUnavailable = await runInspector({
  code: 'edis_inspector_selection_failed',
  message: 'Inspector failed.',
  data: {
    status: 500,
    diagnostic_available: false,
    diagnostic_id: null,
    diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED',
    diagnostics_url: null,
  },
});
assert.equal(inspectorUnavailable.status.children[0].children[0].textContent, 'unavailable');

for (const payload of [
  { code: 'edis_invalid_inspector_selection', message: 'Invalid selection.', data: { status: 400, diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: null, diagnostics_url: null } },
  { code: 'edis_inspector_selection_failed', message: 'Fake ID.', data: { status: 500, diagnostic_available: true, diagnostic_id: 'edis-' + 'b'.repeat(32), diagnostic_persistence_code: null, diagnostics_url: null } },
  { code: 'edis_inspector_selection_failed', message: 'Cross origin.', data: { status: 500, diagnostic_available: true, diagnostic_id: id, diagnostic_persistence_code: null, diagnostics_url: 'https://attacker.test/diagnostics' } },
  { code: 'edis_inspector_selection_failed', message: 'Conflicting aliases.', data: { status: 500, diagnostic_available: true, diagnosticAvailable: false, diagnostic_id: id, diagnostic_persistence_code: null, diagnostics_url: null } },
]) {
  const result = await runInspector(payload);
  assert.equal(result.status.children.length, 0);
  assert.match(result.status.textContent, new RegExp(payload.code));
  assert.match(result.status.textContent, new RegExp(payload.message.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
}

console.log(JSON.stringify({ state: 'PASS', classifier: true, productionConsumer: true, fulfilledWorkerTest: true, inspectorConsumer: true }));
