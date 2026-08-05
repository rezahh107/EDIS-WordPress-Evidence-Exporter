import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = fs.readFileSync(new URL('../assets/js/diagnostics.js', import.meta.url), 'utf8');
const document = {
  querySelector: () => null,
  addEventListener: () => {},
  createElement: () => ({ appendChild() {}, className: '', textContent: '' }),
  body: { appendChild() {} },
  execCommand: () => true,
  createTextNode: (value) => ({ value })
};
const context = { window: {}, document, navigator: {}, fetch() { throw new Error('not used'); }, console };
vm.createContext(context);
vm.runInContext(source, context, { filename: 'diagnostics.js' });
const classify = context.window.EDISDiagnosticEnvelope.classify;
const id = 'edis-diag-' + 'a'.repeat(32);
assert.equal(classify({}), null);
assert.equal(classify({ diagnostic_available: 'false' }), null);
assert.equal(classify({ diagnostic_available: true, diagnostic_id: 'bad', diagnostic_persistence_code: null }), null);
assert.equal(classify({ diagnostic_available: false, diagnostic_id: id, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED' }), null);
assert.equal(classify({ diagnostic_available: true, diagnostic_id: id, diagnostic_persistence_code: null }).state, 'AVAILABLE');
assert.equal(classify({ diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED' }).state, 'CAPACITY');
assert.equal(classify({ diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED' }).state, 'UNAVAILABLE');
console.log(JSON.stringify({ state: 'PASS', cases: 7 }));
