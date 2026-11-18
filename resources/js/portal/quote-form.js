/**
 * The quote tool (/account/offerte-samenstellen/): rental fields only for a rental, and price lines that can be added
 * and removed. Without JavaScript every field is shown and the form still works with its one empty line.
 */
export function initQuoteForms() {
    document.querySelectorAll('[data-quote-details]').forEach((details) => {
        const toggles = [...details.querySelectorAll('[data-quote-detail-toggle]')];
        const all = details.parentElement.querySelector('[data-quote-details-all]');
        const refresh = () => {
            toggles.forEach((toggle) => {
                toggle.closest('.quote-details__item').toggleAttribute('data-excluded', !toggle.checked);
            });
            if (all) {
                const ticked = toggles.filter((toggle) => toggle.checked).length;
                all.checked = ticked === toggles.length;
                all.indeterminate = ticked > 0 && ticked < toggles.length;
            }
        };
        toggles.forEach((toggle) => toggle.addEventListener('change', refresh));
        all?.addEventListener('change', () => {
            toggles.forEach((toggle) => {
                toggle.checked = all.checked;
            });
            refresh();
        });
        refresh();
    });

    document.querySelectorAll('[data-quote-kind]').forEach((kind) => {
        const form = kind.form;
        const toggle = () => {
            form.querySelectorAll('[data-quote-rental]').forEach((element) => {
                element.hidden = kind.value !== 'rent';
            });
        };
        kind.addEventListener('change', toggle);
        form.addEventListener('quote-line-added', toggle);
        toggle();

        const lines = form.querySelector('[data-quote-lines]');
        const template = form.querySelector('[data-quote-line-template]');
        if (!lines || !template) {
            return;
        }
        let nextIndex = lines.querySelectorAll('[data-quote-line]').length;

        form.querySelector('[data-quote-line-add]')?.addEventListener('click', () => {
            lines.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
            form.dispatchEvent(new Event('quote-line-added'));
            lines.lastElementChild?.querySelector('input')?.focus();
        });

        lines.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-quote-line-remove]');
            if (remove) {
                remove.closest('[data-quote-line]')?.remove();
            }
        });
    });
}
