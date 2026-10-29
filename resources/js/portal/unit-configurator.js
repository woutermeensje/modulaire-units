const SLOT_ORDER = ['empty', 'window', 'door'];

function initConfigurator(root) {
  const labels = JSON.parse(root.dataset.slotLabels);
  const icons = JSON.parse(root.dataset.slotIcons);
  const counts = JSON.parse(root.dataset.slotCounts);
  const sizeSelect = root.querySelector('[data-configurator-size]');
  const preview = root.querySelector('[data-configurator-preview]');
  const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const viewerElement = root.querySelector('[data-configurator-viewer]');
  const viewerHelp = root.querySelector('[data-configurator-viewer-help]');
  let timer = null;
  let controller = null;
  let viewer = null;

  const readConfiguration = () => {
    const field = (name) => root.querySelector(`[name="configuration[${name}]"]`).value;
    const walls = {};
    Object.keys(counts[sizeSelect.value]).forEach((wall) => {
      walls[wall] = [...root.querySelectorAll(`[name="configuration[walls][${wall}][]"]:not(:disabled)`)].map((input) => input.value);
    });

    return {
      size: sizeSelect.value,
      walls,
      colors: { cladding: field('colors][cladding'), frame: field('colors][frame') },
      background: field('background'),
    };
  };

  const dimensions = () => ({ length: counts[sizeSelect.value].front, depth: counts[sizeSelect.value].left });
  const backdrops = JSON.parse(root.dataset.backdrops);
  let viewerModule = null;
  const loadViewerModule = () => {
    viewerModule ??= import('../unit-viewer');

    return viewerModule;
  };

  const updateViewer = () => {
    if (!viewer || viewerElement.hidden || !sizeSelect.value) {
      return;
    }

    viewer.update(readConfiguration(), dimensions());
  };

  const showView = async (view) => {
    root.querySelectorAll('[data-configurator-view]').forEach((button) => {
      const isActive = button.dataset.configuratorView === view;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', String(isActive));
    });
    preview.hidden = view !== '2d';
    viewerElement.hidden = view !== '3d';
    viewerHelp.hidden = view !== '3d';

    if (view === '3d' && !viewer) {
      const { createUnitViewer } = await loadViewerModule();
      viewer ??= createUnitViewer(viewerElement, backdrops);
    }
    updateViewer();
  };

  root.querySelectorAll('[data-configurator-view]').forEach((button) => {
    button.addEventListener('click', () => showView(button.dataset.configuratorView));
  });

  const refreshPreview = () => {
    updateViewer();
    clearTimeout(timer);
    timer = setTimeout(async () => {
      if (!sizeSelect.value) {
        return;
      }

      controller?.abort();
      controller = new AbortController();

      const body = new FormData();
      root.querySelectorAll('[name^="configuration"]:not(:disabled)').forEach((input) => body.append(input.name, input.value));

      try {
        const response = await fetch(root.dataset.previewUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
          body,
          signal: controller.signal,
        });
        if (response.ok) {
          preview.innerHTML = await response.text();
        }
      } catch (error) {
        if (error.name !== 'AbortError') {
          throw error;
        }
      }
    }, 150);
  };

  const applySize = () => {
    const size = sizeSelect.value;
    root.querySelectorAll('[data-configurator-body]').forEach((element) => { element.hidden = !size; });

    if (!size) {
      return;
    }

    root.querySelectorAll('[data-configurator-slot]').forEach((slot) => {
      const inUse = Number(slot.dataset.index) < counts[size][slot.dataset.wall];
      slot.hidden = !inUse;
      slot.querySelector('input').disabled = !inUse;
    });
  };

  const setSlot = (slot, value) => {
    slot.querySelector('input').value = value;
    slot.className = `unit-slot unit-slot--${value}`;
    slot.querySelector('i').className = `ph ${icons[value]}`;
    slot.querySelector('span').textContent = labels[value];
  };

  root.querySelectorAll('[data-configurator-slot]').forEach((slot) => {
    slot.addEventListener('click', () => {
      const current = slot.querySelector('input').value;
      setSlot(slot, SLOT_ORDER[(SLOT_ORDER.indexOf(current) + 1) % SLOT_ORDER.length]);
      refreshPreview();
    });
  });

  const applyConfiguration = (configuration) => {
    sizeSelect.value = configuration.size;
    applySize();

    root.querySelectorAll('[data-configurator-slot]').forEach((slot) => {
      setSlot(slot, configuration.walls[slot.dataset.wall][Number(slot.dataset.index)] ?? 'empty');
    });
    Object.entries(configuration.colors).forEach(([name, color]) => {
      root.querySelector(`[name="configuration[colors][${name}]"]`).value = color;
    });
    root.querySelector('[name="configuration[background]"]').value = configuration.background;
  };

  const fillIfEmpty = (id, value) => {
    const field = document.getElementById(id);
    if (field && field.value.trim() === '' && value) {
      field.value = value;
      field.dispatchEvent(new Event('input', { bubbles: true }));
    }
  };

  const generateButton = root.querySelector('[data-configurator-generate]');
  const description = root.querySelector('[data-configurator-description]');
  const status = root.querySelector('[data-configurator-status]');

  generateButton?.addEventListener('click', async () => {
    if (description.value.trim() === '') {
      status.textContent = 'Beschrijf eerst de unit.';
      description.focus();
      return;
    }

    const body = new FormData();
    body.append('description', description.value);
    if (sizeSelect.value) {
      root.querySelectorAll('[name^="configuration"]:not(:disabled)').forEach((input) => body.append(input.name, input.value));
    }

    generateButton.disabled = true;
    status.textContent = 'Claude denkt na…';

    try {
      const response = await fetch(root.dataset.designUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body,
      });
      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        status.textContent = data.message ?? 'Er ging iets mis. Probeer het opnieuw.';
        return;
      }

      applyConfiguration(data.configuration);
      if (root.querySelector('[data-configurator-texts]').checked) {
        fillIfEmpty('title', data.title);
        fillIfEmpty('excerpt', data.excerpt);
        fillIfEmpty('meta_description', data.meta_description);
      }
      refreshPreview();
      status.textContent = 'Klaar. Controleer het voorbeeld en pas zo nodig vakken aan voordat je opslaat.';
    } catch {
      status.textContent = 'Geen verbinding met de server. Probeer het opnieuw.';
    } finally {
      generateButton.disabled = false;
    }
  });

  const form = root.closest('form');
  const photosToggle = root.querySelector('[data-configurator-photos]');
  const photosStatus = root.querySelector('[data-configurator-photos-status]');
  let photosAttached = false;

  // The 3D photos can only be made in the browser, so they are rendered just before the form is sent.
  form?.addEventListener('submit', async (event) => {
    if (photosAttached || !photosToggle.checked || !sizeSelect.value) {
      return;
    }

    event.preventDefault();
    photosStatus.textContent = "3D-foto's maken…";

    try {
      const { renderUnitPhotos } = await loadViewerModule();
      const photos = await renderUnitPhotos(readConfiguration(), dimensions(), backdrops);
      Object.entries(photos).forEach(([view, blob]) => {
        const transfer = new DataTransfer();
        transfer.items.add(new File([blob], `${view}.jpg`, { type: 'image/jpeg' }));
        root.querySelector(`[data-configurator-photo="${view}"]`).files = transfer.files;
      });
    } catch (error) {
      // Without photos the server keeps the current 3D photos or falls back to the 2D drawings.
      console.error(error);
    }

    photosAttached = true;
    form.requestSubmit(event.submitter);
  });

  sizeSelect.addEventListener('change', () => {
    applySize();
    refreshPreview();
  });

  root.querySelectorAll('input[type="color"], select:not([data-configurator-size])').forEach((input) => {
    input.addEventListener('input', refreshPreview);
  });
}

export function initUnitConfigurators() {
  document.querySelectorAll('[data-unit-configurator]').forEach(initConfigurator);
}
