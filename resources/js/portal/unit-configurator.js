import { windowSpans } from '../window-spans';
import { interiorLayout, plannedItems, places } from '../interior-layout';
import { setSelectValue } from './search-select';
import { t } from '../site/i18n';

// Every configuration field that is part of the request; unchosen colour swatches are left out.
const FIELDS = '[name^="configuration"]:not(:disabled):not([type="radio"]:not(:checked)):not([type="checkbox"]:not(:checked))';

// A multiple select (the use of the unit) sends every chosen option, other fields their value.
const appendFields = (root, body) => root.querySelectorAll(FIELDS).forEach((input) => {
  (input.multiple ? [...input.selectedOptions].map((option) => option.value) : [input.value]).forEach((value) => body.append(input.name, value));
});

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
  let currentView = null;

  const modules = () => Number(modulesSelect.value) || 1;
  const floors = () => Number(floorsSelect.value) || 1;
  const slotCount = (size, wall) => counts[size][wall] * (SIDE_WALLS.includes(wall) ? modules() : 1);

  // Only in the quote composer (account/offerte-samenstellen): the use of the unit and a step in front of the doors.
  const usageSelect = root.querySelector('[data-configurator-usage]');
  const stepsToggle = root.querySelector('[data-configurator-steps]');
  const interiorInputs = [...root.querySelectorAll('[name="configuration[interior][]"]')];
  const amountInputs = [...root.querySelectorAll('[data-configurator-amount]')];
  const tentativeInputs = [...root.querySelectorAll('[name="configuration[tentative][]"]')];
  const positionsBox = root.querySelector('[data-configurator-positions]');
  const positionsReset = root.querySelector('[data-configurator-positions-reset]');

  // How many of each piece of furniture or connection, by the group in their name: configuration[furniture][desk].
  const readAmounts = (group) => {
    const amounts = {};
    amountInputs.filter((input) => input.name.startsWith(`configuration[${group}]`)).forEach((input) => {
      const amount = Number(input.value) || 0;
      if (amount > 0) {
        amounts[input.name.slice(input.name.lastIndexOf('[') + 1, -1)] = amount;
      }
    });

    return amounts;
  };

  const readPositions = () => {
    const positions = {};
    positionsBox.querySelectorAll('[data-position]').forEach((input) => {
      const axis = input.name.endsWith('[x]') ? 'x' : 'y';
      positions[input.dataset.position] = { ...positions[input.dataset.position], [axis]: Number(input.value) };
    });

    return positions;
  };

  const readConfiguration = () => {
    const field = (name) => root.querySelector(`[name="configuration[${name}]"]`).value;
    const interior = interiorInputs.filter((input) => input.checked).map((input) => input.value);
    const furniture = readAmounts('furniture');
    const connections = readAmounts('connections');
    const tentative = tentativeInputs.filter((input) => input.checked && !input.disabled).map((input) => input.value);
    const positions = readPositions();
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
      // The frame always has the colour of the walls.
      colors: { cladding: color('cladding'), frame: color('cladding') },
      background: field('background'),
      ...(interior.length ? { interior } : {}),
      ...(usageSelect?.selectedOptions.length ? { usage: [...usageSelect.selectedOptions].map((option) => option.value) } : {}),
      ...(Object.keys(furniture).length ? { furniture } : {}),
      ...(Object.keys(connections).length ? { connections } : {}),
      ...(tentative.length ? { tentative } : {}),
      ...(stepsToggle?.checked ? { steps: true } : {}),
      ...(Object.keys(positions).length ? { positions } : {}),
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

    viewer.update(readConfiguration(), dimensions(), { interior: currentView === 'interior' });
  };

  // "3d" shows the unit from outside, "interior" looks down into the ground floor; both use the same 3D viewer.
  const interiorNote = root.querySelector('[data-configurator-interior-note]');
  const planHelp = root.querySelector('[data-configurator-plan-help]');
  const showView = async (view) => {
    currentView = view;
    root.querySelectorAll('[data-configurator-view]').forEach((button) => {
      const isActive = button.dataset.configuratorView === view;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', String(isActive));
    });
    preview.hidden = view !== '2d';
    planHelp.hidden = view !== '2d';
    viewerElement.hidden = view === '2d';
    viewerHelp.hidden = view === '2d';
    interiorNote.hidden = view !== 'interior' || floors() === 1;

    if (view !== '2d' && !viewer) {
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
      shareCopy.lastChild.textContent = ` ${t('Gekopieerd')}`;
    } catch {
      shareInput.select();
      shareCopy.lastChild.textContent = ` ${t('Kopieer met Ctrl/Cmd+C')}`;
    }
    setTimeout(() => { shareCopy.lastChild.textContent = ` ${t('Kopieer link')}`; }, 2000);
  });

  // "Toevoegen aan offerte" is there from the start, and is offered again after every change.
  const quote = root.querySelector('[data-configurator-quote]');
  const quoteButton = quote?.querySelector('[data-configurator-quote-add]');
  const quoteLabel = quoteButton?.querySelector('span');
  const resetQuote = () => {
    if (!quote) {
      return;
    }

    quote.hidden = false;
    quoteButton.disabled = false;
    quoteLabel.textContent = t('Toevoegen aan offerte');
  };

  // Mirrors partials/header.blade.php: with items in the quote, the main call to action becomes "Mijn offerte".
  const showQuoteCount = (count, quoteUrl) => {
    document.querySelectorAll('[data-quote-cta]').forEach((cta) => {
      cta.href = quoteUrl;
      cta.textContent = t('Mijn offerte');
    });
    document.querySelectorAll('.mh-mobile-nav .mh-btn--nav-quote').forEach((link) => link.remove());

    document.querySelectorAll('.mh-header__quote-icon').forEach((link) => {
      let badge = link.querySelector('.mh-header__quote-badge');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'mh-header__quote-badge';
        link.append(badge);
      }
      badge.textContent = count;
    });
  };

  quoteButton?.addEventListener('click', async () => {
    quoteButton.disabled = true;
    const body = new FormData();
    appendFields(root, body);

    try {
      const response = await fetch(quote.dataset.quoteUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body,
      });
      if (!response.ok) {
        throw new Error(`Status ${response.status}`);
      }

      const data = await response.json();
      quoteLabel.textContent = t('Toegevoegd aan offerte');
      quote.querySelector('[data-configurator-quote-link]').hidden = false;
      showQuoteCount(data.count, data.quote_url);
    } catch {
      quoteLabel.textContent = t('Mislukt, probeer opnieuw');
      quoteButton.disabled = false;
    }
  });

  // Where an item was dragged to on the floor plan, in nominal metres; see App\Support\InteriorLayout.
  const setPosition = (id, x, y) => {
    // Within the unit, as the server only accepts positions inside it; it snaps them to a wall or corner itself.
    const { length, depth } = dimensions();
    const position = { x: Math.max(0, Math.min(length, x)), y: Math.max(0, Math.min(depth, y)) };
    ['x', 'y'].forEach((axis) => {
      let input = positionsBox.querySelector(`[name="configuration[positions][${id}][${axis}]"]`);
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = `configuration[positions][${id}][${axis}]`;
        input.dataset.position = id;
        positionsBox.append(input);
      }
      input.value = String(Math.round(position[axis] * 100) / 100);
    });
    positionsReset.hidden = false;
  };

  const clearPositions = () => {
    positionsBox.replaceChildren();
    positionsReset.hidden = true;
  };

  // Positions of items the unit no longer has are dropped, so they don't come back when the item is chosen again.
  const prunePositions = () => {
    if (!sizeSelect.value) {
      return;
    }

    const configuration = readConfiguration();
    const ids = new Set(LEVELS.slice(0, floors()).flatMap((level) => plannedItems(configuration, level).map(([id]) => id)));
    positionsBox.querySelectorAll('[data-position]').forEach((input) => {
      if (!ids.has(input.dataset.position)) {
        input.remove();
      }
    });
    positionsReset.hidden = !positionsBox.querySelector('[data-position]');
  };

  // "Nog niet zeker" only offers what was chosen.
  const tentativeBox = root.querySelector('[data-configurator-tentative]');
  const updateTentative = () => {
    const chosen = new Set([
      ...interiorInputs.filter((input) => input.checked).map((input) => input.value),
      ...Object.keys(readAmounts('furniture')),
    ]);
    tentativeInputs.forEach((input) => {
      const isChosen = chosen.has(input.value);
      input.disabled = !isChosen;
      input.closest('[data-tentative-for]').hidden = !isChosen;
    });
    if (tentativeBox) {
      tentativeBox.hidden = !sizeSelect.value || chosen.size === 0;
    }
  };

  // The interior on the floor of the top view: every item as a block that can be dragged, or moved with the arrow
  // keys, to where it should go. Positions are percentages of the floor, which has the proportions of the unit.
  const itemIcons = JSON.parse(root.dataset.itemIcons);
  const itemLabels = JSON.parse(root.dataset.itemLabels);
  const itemLayers = [...root.querySelectorAll('[data-configurator-items]')];

  const renderItems = () => {
    if (!sizeSelect.value) {
      return;
    }

    const configuration = readConfiguration();
    const { length, depth } = dimensions();
    itemLayers.forEach((layer) => {
      const level = layer.dataset.level;
      const items = LEVELS.indexOf(level) < floors() ? interiorLayout(configuration, { length, depth }, level).items : [];
      layer.replaceChildren(...items.map((item) => {
        const block = document.createElement('button');
        block.type = 'button';
        block.className = `unit-plan-item unit-plan-item--${item.type}${item.tentative ? ' is-tentative' : ''}`;
        block.dataset.planItem = item.id;
        block.dataset.x = String((item.x1 + item.x2) / 2);
        block.dataset.y = String((item.y1 + item.y2) / 2);
        block.style.left = `${(item.x1 / length) * 100}%`;
        block.style.top = `${(item.y1 / depth) * 100}%`;
        block.style.width = `${((item.x2 - item.x1) / length) * 100}%`;
        block.style.height = `${((item.y2 - item.y1) / depth) * 100}%`;
        block.title = itemLabels[item.type] ?? item.type;
        block.setAttribute('aria-label', block.title);
        block.innerHTML = `<i class="ph ${itemIcons[item.type] ?? 'ph-square'}" aria-hidden="true"></i>`;

        return block;
      }));
    });
  };

  let itemDrag = null;
  root.addEventListener('pointerdown', (event) => {
    const block = event.target.closest('[data-plan-item]');
    if (!block || event.button > 0) {
      return;
    }

    event.preventDefault();
    block.setPointerCapture(event.pointerId);
    block.focus({ preventScroll: true });
    itemDrag = { block, floor: block.parentElement.getBoundingClientRect(), startX: event.clientX, startY: event.clientY, dx: 0, dy: 0 };
    block.classList.add('is-dragging');
  });

  root.addEventListener('pointermove', (event) => {
    if (!itemDrag) {
      return;
    }

    itemDrag.dx = event.clientX - itemDrag.startX;
    itemDrag.dy = event.clientY - itemDrag.startY;
    itemDrag.block.style.transform = `translate(${itemDrag.dx}px, ${itemDrag.dy}px)`;
  });

  const endItemDrag = (event) => {
    if (!itemDrag) {
      return;
    }

    const { block, floor, dx, dy } = itemDrag;
    itemDrag = null;
    block.classList.remove('is-dragging');
    block.style.transform = '';

    if (event.type === 'pointercancel' || Math.hypot(dx, dy) < 4) {
      return;
    }

    const { length, depth } = dimensions();
    setPosition(block.dataset.planItem, Number(block.dataset.x) + (dx / floor.width) * length, Number(block.dataset.y) + (dy / floor.height) * depth);
    refreshPreview();
  };
  root.addEventListener('pointerup', endItemDrag);
  root.addEventListener('pointercancel', endItemDrag);

  // Arrow keys move the focused item 10 cm; its block is drawn anew, so focus moves to the new one.
  root.addEventListener('keydown', (event) => {
    const block = event.target.closest('[data-plan-item]');
    const step = { ArrowLeft: [-0.1, 0], ArrowRight: [0.1, 0], ArrowUp: [0, -0.1], ArrowDown: [0, 0.1] }[event.key];
    if (!block || !step) {
      return;
    }

    event.preventDefault();
    const id = block.dataset.planItem;
    setPosition(id, Number(block.dataset.x) + step[0], Number(block.dataset.y) + step[1]);
    refreshPreview();
    root.querySelector(`[data-plan-item="${id}"]`)?.focus({ preventScroll: true });
  });

  const refreshPreview = () => {
    prunePositions();
    renderItems();
    updateViewer();
    updateShareUrl();
    resetQuote();
    clearTimeout(timer);
    timer = setTimeout(async () => {
      if (!sizeSelect.value) {
        return;
      }

      controller?.abort();
      controller = new AbortController();

      const body = new FormData();
      appendFields(root, body);

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
    updateTentative();

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

  const setAmount = (input, value) => {
    const amount = Math.max(0, Math.min(Number(input.max) || 0, Math.round(Number(value) || 0)));
    input.value = String(amount);
    input.closest('[data-configurator-count]').classList.toggle('is-chosen', amount > 0);
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
    if (places(segment).length) {
      return groups;
    }
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
  // A wall with fixed places (the long walls of a 6 m unit) only shows one block per place; the other slots stay
  // invisible but keep their value. Each place holds the same opening in both its slots, as UnitConfiguration::fillPlaces().
  const applyPlaces = () => {
    // A wall that loses its places (another size) keeps only the opening of each place, not a copy next to it.
    root.querySelectorAll('[data-configurator-slot][data-place]').forEach((slot) => {
      if (slot.dataset.place === 'spacer' && !places(segmentOf(slot)).length) {
        setSlot(slot, 'empty');
      }
      delete slot.dataset.place;
    });
    const walls = new Set([...root.querySelectorAll('[data-configurator-slot]')].map((slot) => `${slot.dataset.level}|${slot.dataset.wall}`));
    walls.forEach((key) => {
      const [level, wall] = key.split('|');
      const first = root.querySelector(`[data-configurator-slot][data-level="${level}"][data-wall="${wall}"]`);
      const slots = wallSlots(first);
      const wallPlaces = places(slots.length ? segmentOf(slots[0]) : 0);
      if (!wallPlaces.length) {
        return;
      }
      const types = wallPlaces.map(([leader, follower]) => {
        const outer = leader < follower ? leader - 1 : leader + 1;
        return [leader, follower, outer].map((index) => (slots[index] ? slotValue(slots[index]) : 'empty')).find((value) => value !== 'empty') ?? 'empty';
      });
      slots.forEach((slot) => {
        slot.dataset.place = 'spacer';
        if (slotValue(slot) !== 'empty') {
          setSlot(slot, 'empty');
        }
      });
      wallPlaces.forEach(([leader, follower], position) => {
        setSlot(slots[leader], types[position]);
        setSlot(slots[follower], types[position]);
        slots[leader].dataset.place = 'leader';
      });
    });
  };
  const placeOf = (slot) => {
    const slots = wallSlots(slot);
    const index = slots.indexOf(slot);
    const place = places(segmentOf(slot)).find(([leader]) => leader === index);

    return place ? [slots[place[0]], slots[place[1]]] : null;
  };

  const markWindowPairs = () => {
    applyPlaces();
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

  // In a fixed place one click goes to the next opening: closed, window, single window, hatch, door.
  const PLACE_CYCLE = ['empty', 'window', 'single_window', 'hatch', 'door'];
  const cycleSlot = (slot) => {
    const slots = wallSlots(slot);
    const index = slots.indexOf(slot);
    const current = slotValue(slot);

    if (places(segmentOf(slot)).length) {
      const place = placeOf(slot);
      if (place) {
        const next = PLACE_CYCLE[(PLACE_CYCLE.indexOf(current) + 1) % PLACE_CYCLE.length];
        place.forEach((part) => setSlot(part, next));
      }

      return;
    }

    if (current === 'window') {
      const group = windowGroups(slots).find((candidate) => candidate.includes(slot));
      group?.forEach((part) => setSlot(part, 'empty'));
      setSlot(slot, 'single_window');
    } else if (current === 'single_window') {
      setSlot(slot, 'door');
    } else if (current === 'door') {
      setSlot(slot, 'hatch');
    } else if (current === 'hatch') {
      setSlot(slot, 'empty');
    } else {
      // A window takes the free slot to the right of the clicked one, or else the one to the left, so two clicks next
      // to each other give two windows side by side. Only on a wall of three slots (3 m) a click on the free middle
      // slot centres a single window on it, as two windows don't fit there anyway.
      const segment = segmentOf(slot);
      const inUnit = (position) => slots[position] && Math.floor(position / segment) === Math.floor(index / segment);
      const free = (position) => inUnit(position) && slotValue(slots[position]) === 'empty';
      const partner = [index + 1, index - 1].find(free);

      if (segment === 3 && index % 3 === 1 && free(index - 1) && free(index + 1)) {
        [index - 1, index, index + 1].forEach((position) => setSlot(slots[position], 'window'));
      } else if (partner === undefined) {
        setSlot(slot, 'single_window');
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
    root.querySelector('[name="configuration[background]"]').value = configuration.background;
    interiorInputs.forEach((input) => { input.checked = (configuration.interior ?? []).includes(input.value); });
    amountInputs.forEach((input) => {
      const [, group, item] = input.name.match(/^configuration\[(\w+)\]\[(\w+)\]$/);
      setAmount(input, configuration[group]?.[item] ?? 0);
    });
    if (stepsToggle) {
      stepsToggle.checked = configuration.steps ?? false;
    }
    updateTentative();
    tentativeInputs.forEach((input) => { input.checked = (configuration.tentative ?? []).includes(input.value); });
    clearPositions();
    Object.entries(configuration.positions ?? {}).forEach(([id, { x, y }]) => setPosition(id, x, y));
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
      status.textContent = t('Beschrijf eerst de unit.');
      description.focus();
      return;
    }

    const body = new FormData();
    body.append('description', description.value);
    if (sizeSelect.value) {
      appendFields(root, body);
    }

    generateButton.disabled = true;
    status.textContent = t('Claude denkt na…');

    try {
      const response = await fetch(root.dataset.designUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body,
      });
      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        status.textContent = data.message ?? t('Er ging iets mis. Probeer het opnieuw.');
        return;
      }

      applyConfiguration(data.configuration);
      if (root.querySelector('[data-configurator-texts]')?.checked) {
        fillIfEmpty('title', data.title);
        fillIfEmpty('meta_description', data.meta_description);
      }
      refreshPreview();
      status.textContent = t('Klaar. Controleer het voorbeeld en pas zo nodig vakken aan voordat je opslaat.');
    } catch {
      status.textContent = t('Geen verbinding met de server. Probeer het opnieuw.');
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
    photosStatus.textContent = t("3D-foto's maken…");

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
      interiorNote.hidden = currentView !== 'interior' || floors() === 1;
      refreshPreview();
    });
  });

  root.querySelectorAll('[name^="configuration[colors]"]').forEach((input) => {
    input.addEventListener('change', refreshPreview);
  });
  interiorInputs.forEach((input) => input.addEventListener('change', () => {
    updateTentative();
    refreshPreview();
  }));
  tentativeInputs.forEach((input) => input.addEventListener('change', refreshPreview));
  stepsToggle?.addEventListener('change', refreshPreview);
  usageSelect?.addEventListener('change', () => {
    updateShareUrl();
    resetQuote();
  });

  amountInputs.forEach((input) => {
    input.addEventListener('change', () => {
      setAmount(input, input.value);
      updateTentative();
      refreshPreview();
    });
    input.closest('[data-configurator-count]').querySelectorAll('[data-count-step]').forEach((button) => {
      button.addEventListener('click', () => {
        setAmount(input, (Number(input.value) || 0) + Number(button.dataset.countStep));
        updateTentative();
        refreshPreview();
      });
    });
  });

  positionsReset.addEventListener('click', () => {
    clearPositions();
    refreshPreview();
  });

  // Dragging an item on the floor plan: the item follows the pointer, and where it is let go becomes its position.
  // The server then draws it snapped to its wall or corner. Coordinates are those of the floor's group in the SVG, in
  // millimetres; data-scale-x/y convert them to the nominal metres of the configuration.
  let drag = null;
  const pointOnPlan = (event, plan) => new DOMPoint(event.clientX, event.clientY).matrixTransform(plan.getScreenCTM().inverse());

  preview.addEventListener('pointerdown', (event) => {
    const item = event.target.closest('[data-item]');
    const plan = item?.closest('[data-plan-level]');
    if (!plan || event.button > 0) {
      return;
    }

    event.preventDefault();
    preview.setPointerCapture(event.pointerId);
    drag = { item, plan, start: pointOnPlan(event, plan), moved: false };
    item.classList.add('is-dragging');
  });

  preview.addEventListener('pointermove', (event) => {
    if (!drag) {
      return;
    }

    const point = pointOnPlan(event, drag.plan);
    drag.dx = point.x - drag.start.x;
    drag.dy = point.y - drag.start.y;
    drag.moved ||= Math.hypot(drag.dx, drag.dy) > 50;
    drag.item.setAttribute('transform', `translate(${drag.dx} ${drag.dy})`);
  });

  const endDrag = (event) => {
    if (!drag) {
      return;
    }

    const { item, plan, moved, dx, dy } = drag;
    drag = null;
    item.classList.remove('is-dragging');
    if (preview.hasPointerCapture(event.pointerId)) {
      preview.releasePointerCapture(event.pointerId);
    }

    if (!moved || event.type === 'pointercancel') {
      item.removeAttribute('transform');
      return;
    }

    setPosition(
      item.dataset.item,
      Number(item.dataset.x) + dx / Number(plan.dataset.scaleX),
      Number(item.dataset.y) + dy / Number(plan.dataset.scaleY),
    );
    refreshPreview();
  };
  preview.addEventListener('pointerup', endDrag);
  preview.addEventListener('pointercancel', endDrag);
  stairsToggle.addEventListener('change', refreshPreview);
  markWindowPairs();
  renderItems();

  if (root.dataset.composer !== undefined) {
    showView('3d');
  }
}

export function initUnitConfigurators() {
  document.querySelectorAll('[data-unit-configurator]').forEach(initConfigurator);
}
