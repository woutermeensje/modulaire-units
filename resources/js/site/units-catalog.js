/**
 * Multi-select "Type unit" filter of the units catalog ([units_catalog] shortcode).
 * Selected values are written to hidden inputs so the GET form submits mh_type[]=slug.
 */
const closeAll = (except = null) => {
    document.querySelectorAll('.mh-units-catalog__select.is-open').forEach((select) => {
        if (select === except) {
            return;
        }
        select.classList.remove('is-open');
        select.querySelector('.mh-units-catalog__select-btn')?.setAttribute('aria-expanded', 'false');
    });
};

document.querySelectorAll('.mh-units-catalog__select').forEach((select) => {
    const name = select.dataset.name;
    const button = select.querySelector('.mh-units-catalog__select-btn');
    const label = select.querySelector('.mh-units-catalog__select-label');
    const search = select.querySelector('.mh-units-catalog__select-search');
    const options = Array.from(select.querySelectorAll('.mh-units-catalog__select-option'));
    const empty = select.querySelector('.mh-units-catalog__select-empty');
    const tags = select.querySelector('.mh-units-catalog__select-tags');
    const hidden = select.querySelector('.mh-units-catalog__select-hidden');
    const placeholder = label?.textContent ?? '';

    const selected = new Map(
        options
            .filter((option) => option.getAttribute('aria-selected') === 'true')
            .map((option) => [option.dataset.value, option.dataset.label]),
    );

    const render = () => {
        options.forEach((option) => {
            const isSelected = selected.has(option.dataset.value);
            option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            option.classList.toggle('is-selected', isSelected);
        });

        if (label) {
            label.textContent = selected.size === 0 ? placeholder : selected.size === 1 ? '1 geselecteerd' : `${selected.size} geselecteerd`;
            label.classList.toggle('is-placeholder', selected.size === 0);
        }

        if (hidden) {
            hidden.innerHTML = '';
            selected.forEach((_, value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                hidden.appendChild(input);
            });
        }

        if (tags) {
            tags.innerHTML = '';
            selected.forEach((text, value) => {
                const tag = document.createElement('span');
                tag.className = 'mh-units-catalog__select-tag';
                tag.textContent = text;

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'mh-units-catalog__select-tag-remove';
                remove.setAttribute('aria-label', `Verwijder ${text}`);
                remove.innerHTML = '&times;';
                remove.addEventListener('click', (event) => {
                    event.stopPropagation();
                    selected.delete(value);
                    render();
                });

                tag.appendChild(remove);
                tags.appendChild(tag);
            });
        }
    };

    button?.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = !select.classList.contains('is-open');
        closeAll(select);
        select.classList.toggle('is-open', willOpen);
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen) {
            search?.focus();
        }
    });

    select.addEventListener('click', (event) => event.stopPropagation());

    options.forEach((option) => {
        option.addEventListener('click', () => {
            const value = option.dataset.value;
            if (selected.has(value)) {
                selected.delete(value);
            } else {
                selected.set(value, option.dataset.label);
            }
            render();
        });
    });

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLowerCase();
        let visible = 0;
        options.forEach((option) => {
            const match = !query || option.dataset.label.toLowerCase().includes(query);
            option.style.display = match ? '' : 'none';
            visible += match ? 1 : 0;
        });
        if (empty) {
            empty.style.display = visible > 0 ? 'none' : 'block';
        }
    });

    if (empty) {
        empty.style.display = 'none';
    }

    render();
});

document.addEventListener('click', () => closeAll());
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAll();
    }
});
