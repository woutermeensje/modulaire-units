import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

// Lists up to this length are picked from directly; longer lists get a search box.
const SEARCH_THRESHOLD = 8;

/**
 * Turns every <select data-search-select> into a styled select (Tom Select); a multiple select always gets a search
 * box at the top of its list and a remove button per choice. The original select keeps its name and value and still fires "change", so forms
 * and scripts listening to it keep working.
 */
export function initSearchSelects(root = document) {
  root.querySelectorAll('select[data-search-select]').forEach((select) => {
    if (select.tomselect) {
      return;
    }

    const settings = {
      allowEmptyOption: true,
      maxOptions: null,
      plugins: select.multiple ? ['remove_button', 'dropdown_input'] : [],
      render: {
        no_results: () => '<div class="no-results">Geen resultaten</div>',
      },
    };

    // Leaving controlInput out keeps Tom Select's search box; passing undefined would remove it.
    if (!select.multiple && select.options.length <= SEARCH_THRESHOLD) {
      settings.controlInput = null;
    }

    new TomSelect(select, settings);
  });
}

/**
 * Set the value of a select from a script, whether or not it has been turned into a Tom Select.
 */
export function setSelectValue(select, value) {
  if (select.tomselect) {
    select.tomselect.setValue(String(value), true);
  } else {
    select.value = value;
  }
}
