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
const context = {
  window: {},
  document,
  navigator: {},
  fetch() { throw new Error('not used'); },
  console
};
vm.createContext(context);
vm.runInContext(source, context, { filename: 'diagnostics.js' });

const classify = context.window.EDISDiagnosticEnvelope.classify;
const id = 'edis-diag-' + 'a'.repeat(32);
const cases = [];

function expectNull(payload) {
  assert.equal(classify(payload), null);
  cases.push('null');
}

function expectState(payload, state) {
  assert.equal(classify(payload).state, state);
  cases.push(state);
}

expectNull({});
expectNull({ diagnostic_available: 'false' });
expectNull({ diagnostic_available: true, diagnostic_id: 'bad', diagnostic_persistence_code: null });
expectNull({ diagnostic_available: false, diagnostic_id: id, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED' });
expectNull({ diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED', diagnostics_url: '/fabricated' });
expectNull({ diagnostic_available: true, diagnosticAvailable: false, diagnostic_id: id, diagnostic_persistence_code: null });
expectNull({ diagnostic_available: true, diagnostic_id: id, diagnosticId: 'edis-diag-' + 'b'.repeat(32), diagnostic_persistence_code: null });
expectNull({ diagnosticAvailable: true, diagnosticId: id, diagnosticPersistenceCode: null, safeResponseMetadata: [] });

expectState({ diagnostic_available: true, diagnostic_id: id, diagnostic_persistence_code: null }, 'AVAILABLE');
expectState({ diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED' }, 'CAPACITY');
expectState({ diagnostic_available: false, diagnostic_id: null, diagnostic_persistence_code: 'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED' }, 'UNAVAILABLE');
expectState({ diagnosticAvailable: true, diagnosticId: id, diagnosticPersistenceCode: null, diagnosticsUrl: '/diagnostic', safeResponseMetadata: { bounded: true } }, 'AVAILABLE');
expectState({ diagnosticAvailable: false, diagnosticId: null, diagnosticPersistenceCode: 'EDIS_DIAGNOSTIC_CAPACITY_REACHED', diagnosticsUrl: null }, 'CAPACITY');
expectState({ diagnostic_available: true, diagnosticAvailable: true, diagnostic_id: id, diagnosticId: id, diagnostic_persistence_code: null, diagnosticPersistenceCode: null }, 'AVAILABLE');

console.log(JSON.stringify({ state: 'PASS', cases: cases.length }));
