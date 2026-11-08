import { t } from './i18n';

const popup = document.getElementById('mh-discount-popup');
const delayMs = 8000;

if (popup && typeof popup.showModal === 'function') {
    // Keyed by code, so a new code is shown again to visitors who closed an earlier one.
    const storageKey = `mh_discount_popup_seen_${popup.dataset.code}`;

    const hasSeen = () => {
        try {
            return localStorage.getItem(storageKey) === '1';
        } catch {
            return false;
        }
    };

    const markSeen = () => {
        try {
            localStorage.setItem(storageKey, '1');
        } catch {
            // Ignore unavailable storage.
        }
    };

    if (!hasSeen()) {
        window.setTimeout(() => {
            if (!document.querySelector('dialog[open]')) {
                popup.showModal();
                markSeen();
            }
        }, delayMs);
    }

    popup.querySelectorAll('[data-discount-close]').forEach((element) => {
        element.addEventListener('click', () => popup.close());
    });

    popup.addEventListener('click', (event) => {
        if (event.target === popup) {
            popup.close();
        }
    });

    const copyButton = popup.querySelector('[data-discount-copy]');

    copyButton?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(popup.dataset.code);
            copyButton.textContent = t('Gekopieerd!');
        } catch {
            copyButton.textContent = t('Kopiëren mislukt');
        }

        window.setTimeout(() => {
            copyButton.textContent = t('Kopieer');
        }, 2000);
    });
}
