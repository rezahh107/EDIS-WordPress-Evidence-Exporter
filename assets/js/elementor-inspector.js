(() => {
  'use strict';

  const cfg = window.EDISElementorInspector || {};
  const strings = cfg.strings || {};
  const selections = new Map();
  const installedHooks = new Set();
  let activeDocumentId = null;
  let installed = false;

  const format = (text, value) => String(text || '').replace('%d', String(value));
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
  }[character]));

  function currentDocumentId() {
    const candidates = [
      window.elementor?.documents?.currentDocument?.id,
      window.elementor?.documents?.currentDocument?.config?.id,
      window.elementor?.config?.document?.id,
      window.elementor?.config?.post_id,
    ];
    for (const value of candidates) {
      const numeric = Number(value);
      if (Number.isInteger(numeric) && numeric > 0) return String(numeric);
    }
    return null;
  }

  function validElementId(value) {
    return typeof value === 'string' && value.length > 0 && value.length <= 128 && /^[A-Za-z0-9_-]+$/.test(value);
  }

  function isElementView(value) {
    return Boolean(value?.model && typeof value.model.get === 'function');
  }

  function identityFromView(view) {
    if (!isElementView(view)) return null;
    const model = view.model;
    const get = (name) => model.get(name);
    const id = get('id');
    const documentId = currentDocumentId();
    if (!documentId || !validElementId(id)) return null;
    return {
      document_id: documentId,
      elementor_element_id: id,
      element_type: String(get('elType') || get('widgetType') || 'unknown'),
      display_label: String(get('title') || get('label') || get('widgetType') || get('elType') || id),
    };
  }

  function unsavedChangesState() {
    try {
      if (window.elementor?.saver && typeof window.elementor.saver.isEditorChanged === 'function') {
        return window.elementor.saver.isEditorChanged() ? 'TRUE' : 'FALSE';
      }
      return 'UNAVAILABLE';
    } catch (error) {
      return 'ERROR';
    }
  }

  function tray() {
    let node = document.getElementById('edis-inspector-tray');
    if (node) return node;
    node = document.createElement('section');
    node.id = 'edis-inspector-tray';
    node.className = 'edis-inspector-tray';
    node.setAttribute('role', 'region');
    node.setAttribute('aria-label', strings.groupTitle || 'EDIS Evidence Inspector');
    node.innerHTML = '<strong data-edis-count></strong><ul data-edis-items></ul><p data-edis-status aria-live="polite"></p><div><button type="button" data-edis-open></button><button type="button" data-edis-clear></button></div>';
    node.querySelector('[data-edis-open]').textContent = strings.openSelection || 'Export selected elements';
    node.querySelector('[data-edis-clear]').textContent = strings.clearSelection || 'Clear';
    node.querySelector('[data-edis-open]').addEventListener('click', () => openExport(Array.from(selections.values())));
    node.querySelector('[data-edis-clear]').addEventListener('click', () => {
      selections.clear();
      renderTray();
    });
    document.body.appendChild(node);
    return node;
  }

  function status(message, isError = false) {
    const node = tray().querySelector('[data-edis-status]');
    node.textContent = message || '';
    node.classList.toggle('edis-inspector-error', Boolean(isError));
  }

  function synchronizeDocument() {
    const current = currentDocumentId();
    if (activeDocumentId === null) activeDocumentId = current;
    if (current && activeDocumentId && current !== activeDocumentId) {
      selections.clear();
      activeDocumentId = current;
      renderTray();
      status(strings.documentChanged || 'The Elementor document changed, so the previous EDIS selection was cleared.');
    }
    return current;
  }

  async function openExport(items) {
    const documentId = synchronizeDocument();
    if (!documentId || !Array.isArray(items) || items.length === 0) return;
    const bounded = items.slice(0, Number(cfg.maxSelections || 50));
    if (bounded.some((item) => item.document_id !== documentId)) {
      status(strings.documentChanged || 'The selection belongs to another document.', true);
      return;
    }
    status('');
    try {
      const response = await fetch(cfg.selectionEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce},
        body: JSON.stringify({
          document_id: Number(documentId),
          editor_unsaved_changes_state: unsavedChangesState(),
          selection: bounded.map((item) => ({
            document_id: item.document_id,
            elementor_element_id: item.elementor_element_id,
            element_type: item.element_type || 'unknown',
            include_descendants: Boolean(item.include_descendants),
          })),
        }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.create_export_url) {
        throw new Error(payload?.message || strings.requestFailed || 'Request failed.');
      }
      window.open(payload.create_export_url, '_blank', 'noopener');
    } catch (error) {
      status(error?.message || strings.requestFailed || 'The Inspector selection could not be transferred securely.', true);
    }
  }

  function renderTray() {
    const node = tray();
    node.hidden = selections.size === 0;
    node.querySelector('[data-edis-count]').textContent = format(strings.selectionCount || 'EDIS selection: %d', selections.size);
    const list = node.querySelector('[data-edis-items]');
    list.innerHTML = Array.from(selections.entries()).map(([key, item]) => `<li><span>${escapeHtml(item.display_label || item.elementor_element_id)} — ${item.include_descendants ? 'SUBTREE' : 'ELEMENT'}</span><button type="button" data-edis-remove="${escapeHtml(key)}" aria-label="${escapeHtml(strings.removeItem || 'Remove selection')}">×</button></li>`).join('');
    list.querySelectorAll('[data-edis-remove]').forEach((button) => button.addEventListener('click', () => {
      selections.delete(button.dataset.edisRemove);
      renderTray();
    }));
  }

  function toggleSelection(identity, includeDescendants) {
    synchronizeDocument();
    if (!identity) {
      status(strings.missingIdentity || 'Missing element identity.', true);
      return;
    }
    const key = `${identity.document_id}:${identity.elementor_element_id}`;
    if (selections.has(key)) {
      selections.delete(key);
    } else {
      if (selections.size >= Number(cfg.maxSelections || 50)) {
        status(strings.selectionLimit || 'Selection limit reached.', true);
        return;
      }
      selections.set(key, {...identity, include_descendants: Boolean(includeDescendants)});
    }
    renderTray();
  }

  function actionsFor(view) {
    const identity = identityFromView(view);
    const key = identity ? `${identity.document_id}:${identity.elementor_element_id}` : '';
    const selected = key !== '' && selections.has(key);
    return [
      {name: 'edis-export-element', icon: 'eicon-download-bold', title: strings.exportElement || 'Export this element', isEnabled: () => Boolean(identity), callback: () => identity && openExport([{...identity, include_descendants: false}])},
      {name: 'edis-export-subtree', icon: 'eicon-navigator', title: strings.exportSubtree || 'Export this subtree + required dependencies', isEnabled: () => Boolean(identity), callback: () => identity && openExport([{...identity, include_descendants: true}])},
      {name: 'edis-toggle-selection', icon: selected ? 'eicon-close' : 'eicon-plus-circle', title: selected ? (strings.removeSelection || 'Remove from EDIS selection') : (strings.addSelection || 'Add subtree to EDIS selection'), isEnabled: () => Boolean(identity), callback: () => toggleSelection(identity, true)},
    ];
  }

  function appendInspectorGroup(groups, view) {
    if (!Array.isArray(groups) || !isElementView(view)) return groups;
    const next = groups.map((group) => ({...group, actions: Array.isArray(group.actions) ? [...group.actions] : []}));
    const existing = next.find((group) => group.name === 'edis-evidence-inspector');
    if (existing) existing.actions = actionsFor(view);
    else next.push({name: 'edis-evidence-inspector', actions: actionsFor(view)});
    return next;
  }

  function registerViewAwareHook(name) {
    if (installedHooks.has(name)) return;
    window.elementor.hooks.addFilter(name, (groups, view) => appendInspectorGroup(groups, view));
    installedHooks.add(name);
  }

  function install() {
    if (installed || !window.elementor?.hooks?.addFilter) return;
    installed = true;
    activeDocumentId = currentDocumentId();

    // These official legacy hooks provide the element View as the second argument.
    ['section', 'column', 'widget'].forEach((elementType) => {
      registerViewAwareHook(`elements/${elementType}/contextMenuGroups`);
    });

    // The documented generic hook normally supplies elementType, not a View. Keep a guarded
    // adapter for versions/add-ons that provide a third view argument; never treat elementType as identity.
    window.elementor.hooks.addFilter('elements/context-menu/groups', (groups, elementType, maybeView) => {
      const view = isElementView(maybeView) ? maybeView : null;
      return view ? appendInspectorGroup(groups, view) : groups;
    });
    installedHooks.add('elements/context-menu/groups');
    renderTray();
  }

  window.addEventListener('elementor/init', install, {once: true});
})();
