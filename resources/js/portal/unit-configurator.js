const SLOT_ORDER = ['empty', 'window', 'door'];

function initConfigurator(root) {
  const labels = JSON.parse(root.dataset.slotLabels);
  const icons = JSON.parse(root.dataset.slotIcons);
  const counts = JSON.parse(root.dataset.slotCounts);
  const sizeSelect = root.querySelector('[data-configurator-size]');
  const preview = root.querySelector('[data-configurator-preview]');
  const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  let timer = null;
  let controller = null;

  const refreshPreview = () => {
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

  root.querySelectorAll('[data-configurator-slot]').forEach((slot) => {
    slot.addEventListener('click', () => {
      const input = slot.querySelector('input');
      const next = SLOT_ORDER[(SLOT_ORDER.indexOf(input.value) + 1) % SLOT_ORDER.length];

      input.value = next;
      slot.className = `unit-slot unit-slot--${next}`;
      slot.querySelector('i').className = `ph ${icons[next]}`;
      slot.querySelector('span').textContent = labels[next];
      refreshPreview();
    });
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
