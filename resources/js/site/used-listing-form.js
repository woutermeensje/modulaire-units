import { t } from './i18n';

/**
 * "Units aanbieden": refuse more photos than allowed before the form is sent.
 */
document.querySelectorAll('input[type="file"][data-max-files]').forEach((input) => {
    const maxFiles = Number(input.dataset.maxFiles);

    input.addEventListener('change', () => {
        input.setCustomValidity(input.files.length > maxFiles ? t('Je kunt maximaal :count afbeeldingen uploaden.', { count: maxFiles }) : '');
        input.reportValidity();
    });
});
