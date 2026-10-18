const DEFAULT_SELECTOR = 'textarea:not([data-quill="off"])';
const instances = [];

let formsBound = false;

export const quillToolbar = [
  [{ header: [2, 3, false] }],
  ['bold', 'italic', 'underline'],
  [{ list: 'ordered' }, { list: 'bullet' }],
  ['link'],
];

export const quillFormats = [
  'header',
  'bold',
  'italic',
  'underline',
  'list',
  'link',
];

export function looksLikeHtml(value) {
  return /<\/?[a-z][\s\S]*>/i.test(value || '');
}

export function stripHtml(value) {
  return String(value || '').replace(/<[^>]*>/g, '').trim();
}

function resolveElement(target) {
  if (typeof target === 'string') {
    return document.querySelector(target);
  }

  return target || null;
}

function getTextareaOptions(textarea) {
  return {
    exposeAs: textarea.dataset.quillExposeAs || '',
    editorHeight: textarea.dataset.editorHeight || textarea.dataset.editorMinHeight || '',
  };
}

export function stripBackgroundStyles(value) {
  if (!looksLikeHtml(value)) return value || '';

  const parsed = new DOMParser().parseFromString(value || '', 'text/html');

  parsed.body.querySelectorAll('*').forEach((element) => {
    element.removeAttribute('bgcolor');

    Array.from(element.style).forEach((property) => {
      if (property.toLowerCase().startsWith('background')) {
        element.style.removeProperty(property);
      }
    });

    if (!element.getAttribute('style')) {
      element.removeAttribute('style');
    }
  });

  return parsed.body.innerHTML;
}

function stripBackgroundFromDelta(delta) {
  const Delta = window.Quill?.import?.('delta');
  if (!Delta || !delta?.ops) return delta;

  return new Delta(delta.ops.map((op) => {
    if (!op.attributes?.background) return op;

    const attributes = { ...op.attributes };
    delete attributes.background;

    const cleanedOp = { ...op };

    if (Object.keys(attributes).length) {
      cleanedOp.attributes = attributes;
    } else {
      delete cleanedOp.attributes;
    }

    return cleanedOp;
  }));
}

function registerClipboardCleaners(quill) {
  quill.clipboard.addMatcher(Node.ELEMENT_NODE, (_node, delta) => stripBackgroundFromDelta(delta));
}

function syncInstance(instance) {
  const html = stripBackgroundStyles(instance.quill.root.innerHTML);
  const text = instance.quill.getText().trim();

  instance.textarea.value = text ? html : '';
}

function validateFormQuillFields(form) {
  let firstInvalid = null;

  instances.forEach((instance) => {
    if (!form.contains(instance.textarea)) return;

    instance.sync();

    const isRequired = instance.textarea.dataset.quillRequired === '1';
    const isEmpty = stripHtml(instance.textarea.value) === '';

    if (isRequired && isEmpty) {
      instance.wrapper.classList.add('is-invalid');
      if (!firstInvalid) firstInvalid = instance;
    } else {
      instance.wrapper.classList.remove('is-invalid');
    }
  });

  return firstInvalid;
}

function bindFormValidation() {
  if (formsBound) return;

  document.addEventListener('submit', (event) => {
    if (!(event.target instanceof HTMLFormElement)) return;

    const firstInvalid = validateFormQuillFields(event.target);

    if (firstInvalid) {
      event.preventDefault();
      firstInvalid.quill.focus();
    }
  });

  formsBound = true;
}

export function initQuillEditor(target, index = 0, options = {}) {
  if (typeof window.Quill === 'undefined') return null;

  const textarea = resolveElement(target);
  if (!textarea || textarea.dataset.quillEnhanced === '1') return null;

  const dataOptions = getTextareaOptions(textarea);
  const wrapper = document.createElement('div');
  wrapper.className = 'quill-field';

  const editor = document.createElement('div');
  editor.className = 'quill-field__editor';
  editor.id = textarea.id ? `${textarea.id}__quill` : `quill_editor_${index}`;

  const editorHeight = options.height || options.editorHeight || dataOptions.editorHeight;
  if (editorHeight) {
    // Een kaal getal ("160") is geen geldige CSS-hoogte; behandel het als pixels.
    editor.style.setProperty('--quill-editor-height', /^\d+$/.test(String(editorHeight)) ? `${editorHeight}px` : editorHeight);
  }

  textarea.parentNode.insertBefore(wrapper, textarea);
  wrapper.appendChild(editor);
  wrapper.appendChild(textarea);

  if (textarea.required) {
    textarea.dataset.quillRequired = '1';
    textarea.required = false;
  }

  textarea.classList.add('quill-field__source');
  textarea.dataset.quillEnhanced = '1';

  const quill = new window.Quill(editor, {
    theme: 'snow',
    placeholder: textarea.getAttribute('placeholder') || '',
    formats: options.formats || quillFormats,
    modules: {
      toolbar: options.toolbar || quillToolbar,
    },
  });

  registerClipboardCleaners(quill);

  const initialValue = textarea.value || '';
  if (initialValue.trim() !== '') {
    if (looksLikeHtml(initialValue)) {
      quill.clipboard.dangerouslyPasteHTML(stripBackgroundStyles(initialValue));
    } else {
      quill.setText(initialValue);
    }
  }

  const instance = {
    textarea,
    quill,
    wrapper,
    sync: () => syncInstance(instance),
  };

  instance.sync();
  quill.on('text-change', instance.sync);
  instances.push(instance);

  const exposeAs = options.exposeAs || dataOptions.exposeAs;
  if (exposeAs) {
    window[exposeAs] = quill;
  }

  bindFormValidation();

  return instance;
}

export function initQuillEditors(selector = DEFAULT_SELECTOR, options = {}) {
  if (typeof window.Quill === 'undefined') return [];

  return Array.from(document.querySelectorAll(selector))
    .map((textarea, index) => initQuillEditor(textarea, index, options))
    .filter(Boolean);
}

export function getQuillInstances() {
  return instances;
}

export function exposeQuillHelpers() {
  window.SustainableQuillEditor = {
    getInstances: getQuillInstances,
    init: initQuillEditor,
    initAll: initQuillEditors,
    stripBackgroundStyles,
    stripHtml,
    formats: quillFormats,
    toolbar: quillToolbar,
  };
}
