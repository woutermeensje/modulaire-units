import { initSearchSelects } from './portal/search-select';
import { initUnitConfigurators } from './portal/unit-configurator';

/**
 * The public unit composer: the configurator from the portal, on its own page of the website.
 */
document.addEventListener('DOMContentLoaded', () => {
    initSearchSelects();
    initUnitConfigurators();
});
