import { windowSpans } from '../window-spans';
import { setSelectValue } from './search-select';

// Every configuration field that is part of the request; unchosen colour swatches are left out.
const FIELDS = '[name^="configuration"]:not(:disabled):not([type="radio"]:not(:checked)):not([type="checkbox"]:not(:checked))';

// The left and right walls run along every coupled unit; the front and back walls belong to one unit each.
const SIDE_WALLS = ['left', 'right'];

// The sets of walls per layer, from the ground up, as in App\Support\UnitConfiguration::LEVELS.
const LEVELS = ['walls', 'upper_walls'];

function initConfigurator(root) {
  const labels = JSON.parse(root.dataset.slotLabels);
  const icons = JSON.parse(root.dataset.slotIcons);
  const counts = JSON.parse(root.dataset.slotCounts);
  const sizeSelect = root.querySelector('[data-configurator-size]');
  const modulesSelect = root.querySelector('[data-configurator-modules]');
  const floorsSelect = root.querySelector('[data-configurator-floors]');
  const stairsToggle = root.querySelector('[data-configurator-stairs]');
  const preview = root.querySelector('[data-configurator-preview]');
  const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const viewerElement = root.querySelector('[data-configurator-viewer]');
  const viewerHelp = root.querySelector('[data-configurator-viewer-help]');
  let timer = null;
  let controller = null;
  let viewer = null;

  const modules = () => Number(modulesSelect.value) || 1;
  const floors = () => Number(floorsSelect.value) || 1;
  const slotCount = (size, wall) => counts[size][wall] * (SIDE_WALLS.includes(wall) ? modules() : 1);

  const readConfiguration = () => {
    const field = (name) => root.querySelector(`[name="configuration[${name}]"]`).value;
    const color = (part) => root.querySelector(`[name="configuration[colors][${part}]"]:checked`)?.value;
    const levels = {};
    LEVELS.slice(0, floors()).forEach((level) => {
      levels[level] = {};
      Object.keys(counts[sizeSelect.value]).forEach((wall) => {
        levels[level][wall] = [...root.querySelectorAll(`[name="configuration[${level}][${wall}][]"]:not(:disabled)`)].map((input) => input.value);
      });
    });

    return {
      size: sizeSelect.value,
      modules: modules(),
      floors: floors(),
      ...levels,
      stairs: floors() > 1 && stairsToggle.checked,
      colors: { cladding: color('cladding'), frame: color('frame') },
      background: field('background'),
    };
  };

  const dimensions = () => ({ length: counts[sizeSelect.value].front, depth: counts[sizeSelect.value].left * modules(), floors: floors() });
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
      viewer ??= createUnitViewer(viewerElement, backdrops, root.dataset.composer !== undefined ? { view: 'corner' } : {});
    }
    updateViewer();
  };

  root.querySelectorAll('[data-configurator-view]').forEach((button) => {
    button.addEventListener('click', () => showView(button.dataset.configuratorView));
  });

  // In the composer the composition lives in the URL, so every change gives a new link to share.
  const shareInput = root.querySelector('[data-configurator-share-url]');
  const updateShareUrl = () => {
    if (!shareInput || !sizeSelect.value) {
      return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set(root.dataset.shareParam, btoa(JSON.stringify(readConfiguration())).replace(/\+/g, '-').replace(/\//g, '_'));
    window.history.replaceState(null, '', url);
    shareInput.value = url.toString();
  };

  const shareCopy = root.querySelector('[data-configurator-share-copy]');
  shareCopy?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(shareInput.value);
      shareCopy.lastChild.textContent = ' Gekopieerd';
    } catch {
      shareInput.select();
      shareCopy.lastChild.textContent = ' Kopieer met Ctrl/Cmd+C';
    }
    setTimeout(() => { shareCopy.lastChild.textContent = ' Kopieer link'; }, 2000);
  });

  const refreshPreview = () => {
    updateViewer();
    updateShareUrl();
    clearTimeout(timer);
    timer = setTimeout(async () => {
      if (!sizeSelect.value) {
        return;
      }

      controller?.abort();
      controller = new AbortController();

      const body = new FormData();
      root.querySelectorAll(FIELDS).forEach((input) => body.append(input.name, input.value));

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

    root.querySelectorAll('[data-configurator-level]').forEach((level) => {
      level.hidden = Number(level.dataset.floor) >= floors();
      level.querySelector('[data-configurator-level-title]').hidden = floors() === 1;
    });

    root.querySelectorAll('[data-configurator-slot]').forEach((slot) => {
      const inUse = LEVELS.indexOf(slot.dataset.level) < floors() && Number(slot.dataset.index) < slotCount(size, slot.dataset.wall);
      slot.hidden = !inUse;
      slot.querySelector('input').disabled = !inUse;
    });
    markWindowPairs();

    root.querySelectorAll('[data-configurator-module]').forEach((module) => {
      module.hidden = Number(module.dataset.configuratorModule) > modules();
      module.querySelector('[data-configurator-module-label]').hidden = modules() === 1;
    });
  };

  const setSlot = (slot, value) => {
    slot.querySelector('input').value = value;
    slot.className = `unit-slot unit-slot--${value}`;
    slot.querySelector('i').className = `ph ${icons[value]}`;
    slot.querySelector('span').textContent = labels[value];
  };

  // A window takes two neighbouring slots within one unit, or three when it sits centred on the middle one; runs of
  // window slots are split up the same way as App\Support\UnitConfiguration::openings() does.
  const slotValue = (slot) => slot.querySelector('input').value;
  const wallSlots = (slot) => [...root.querySelectorAll(`[data-configurator-slot][data-level="${slot.dataset.level}"][data-wall="${slot.dataset.wall}"]`)]
    .filter((candidate) => !candidate.querySelector('input').disabled);
  const segmentOf = (slot) => counts[sizeSelect.value][slot.dataset.wall];
  const windowGroups = (slots) => {
    const groups = [];
    const segment = slots.length ? segmentOf(slots[0]) : 1;
    let index = 0;

    while (index < slots.length) {
      if (slotValue(slots[index]) !== 'window') {
        index++;
        continue;
      }

      const segmentEnd = (Math.floor(index / segment) + 1) * segment;
      let end = index;
      while (end < Math.min(slots.length, segmentEnd) && slotValue(slots[end]) === 'window') {
        end++;
      }
      const spans = windowSpans(end - index);
      spans.forEach((span) => {
        groups.push(slots.slice(index, index + span));
        index += span;
      });
      if (spans.length === 0) {
        index = end;
      }
    }

    return groups;
  };
  const markWindowPairs = () => {
    root.querySelectorAll('.unit-slot--pair-start, .unit-slot--pair-middle, .unit-slot--pair-end').forEach((slot) => {
      slot.classList.remove('unit-slot--pair-start', 'unit-slot--pair-middle', 'unit-slot--pair-end');
    });
    const walls = new Set([...root.querySelectorAll('[data-configurator-slot]')].map((slot) => `${slot.dataset.level}|${slot.dataset.wall}`));
    walls.forEach((key) => {
      const [level, wall] = key.split('|');
      const first = root.querySelector(`[data-configurator-slot][data-level="${level}"][data-wall="${wall}"]`);
      windowGroups(wallSlots(first)).forEach((group) => {
        group.forEach((slot, position) => {
          const part = position === 0 ? 'start' : (position === group.length - 1 ? 'end' : 'middle');
          slot.classList.add(`unit-slot--pair-${part}`);
        });
      });
    });
  };

  const cycleSlot = (slot) => {
    const slots = wallSlots(slot);
    const index = slots.indexOf(slot);
    const current = slotValue(slot);

    if (current === 'window') {
      const group = windowGroups(slots).find((candidate) => candidate.includes(slot));
      group?.forEach((part) => setSlot(part, 'empty'));
      setSlot(slot, 'door');
    } else if (current === 'door') {
      setSlot(slot, 'empty');
    } else {
      // Centre the window on the clicked slot when both neighbours are free and no other window touches them;
      // otherwise it takes the free slot next to it.
      const segment = segmentOf(slot);
      const inUnit = (position) => slots[position] && Math.floor(position / segment) === Math.floor(index / segment);
      const free = (position) => inUnit(position) && slotValue(slots[position]) === 'empty';
      const noWindow = (position) => !inUnit(position) || slotValue(slots[position]) !== 'window';
      const partner = [index + 1, index - 1].find(free);

      if (free(index - 1) && free(index + 1) && noWindow(index - 2) && noWindow(index + 2)) {
        [index - 1, index, index + 1].forEach((position) => setSlot(slots[position], 'window'));
      } else if (partner === undefined) {
        setSlot(slot, 'door');
      } else {
        setSlot(slot, 'window');
        setSlot(slots[partner], 'window');
      }
    }
  };

  root.querySelectorAll('[data-configurator-slot]').forEach((slot) => {
    slot.addEventListener('click', () => {
      cycleSlot(slot);
      markWindowPairs();
      refreshPreview();
    });
  });

  const applyConfiguration = (configuration) => {
    setSelectValue(sizeSelect, configuration.size);
    setSelectValue(modulesSelect, configuration.modules ?? 1);
    setSelectValue(floorsSelect, configuration.floors ?? 1);
    stairsToggle.checked = configuration.stairs ?? true;
    applySize();

    root.querySelectorAll('[data-configurator-slot]').forEach((slot) => {
      setSlot(slot, configuration[slot.dataset.level]?.[slot.dataset.wall]?.[Number(slot.dataset.index)] ?? 'empty');
    });
    markWindowPairs();
    Object.entries(configuration.colors).forEach(([part, color]) => {
      const swatch = root.querySelector(`[name="configuration[colors][${part}]"][value="${color}"]`);
      if (swatch) {
        swatch.checked = true;
      }
    });
    setSelectValue(root.querySelector('[name="configuration[background]"]'), configuration.background);
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
      root.querySelectorAll(FIELDS).forEach((input) => body.append(input.name, input.value));
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
      if (root.querySelector('[data-configurator-texts]')?.checked) {
        fillIfEmpty('title', data.title);
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

  [modulesSelect, floorsSelect].forEach((select) => {
    select.addEventListener('change', () => {
      applySize();
      refreshPreview();
    });
  });

  root.querySelectorAll('[name^="configuration[colors]"]').forEach((input) => {
    input.addEventListener('change', refreshPreview);
  });
  root.querySelector('[name="configuration[background]"]').addEventListener('change', refreshPreview);
  stairsToggle.addEventListener('change', refreshPreview);
  markWindowPairs();

  if (root.dataset.composer !== undefined) {
    showView('3d');
  }
}

export function initUnitConfigurators() {
  document.querySelectorAll('[data-unit-configurator]').forEach(initConfigurator);
}
