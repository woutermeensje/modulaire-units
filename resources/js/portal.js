import { exposeQuillHelpers, initQuillEditors } from './portal/quill-editor';
import { initUnitConfigurators } from './portal/unit-configurator';

exposeQuillHelpers();

document.addEventListener('DOMContentLoaded', () => {
    initQuillEditors();
    initUnitConfigurators();

    const shell = document.querySelector('[data-backend-shell]');
    const toggle = document.querySelector('[data-backend-toggle]');
    toggle?.addEventListener('click', () => shell?.classList.toggle('is-sidebar-open'));

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-slug-source]').forEach((source) => {
        const target = document.getElementById(source.dataset.slugSource);
        if (!target || target.value !== '') {
            return;
        }
        source.addEventListener('input', () => {
            if (target.dataset.touched) {
                return;
            }
            target.value = source.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        });
        target.addEventListener('input', () => { target.dataset.touched = '1'; });
    });
});
