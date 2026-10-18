/**
 * Minimal replacements for the Elementor frontend scripts used by the imported page content:
 * toggles/accordions (FAQ) and elements that Elementor only reveals with JavaScript.
 */
document.querySelectorAll('.elementor-toggle .elementor-tab-title, .elementor-accordion .elementor-tab-title').forEach((title) => {
    const content = title.parentElement?.querySelector('.elementor-tab-content');
    if (!content) {
        return;
    }

    content.style.display = title.classList.contains('elementor-active') ? 'block' : 'none';

    const toggle = () => {
        const isOpen = title.classList.toggle('elementor-active');
        content.classList.toggle('elementor-active', isOpen);
        content.style.display = isOpen ? 'block' : 'none';
        title.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    title.addEventListener('click', toggle);
    title.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggle();
        }
    });
});

document.querySelectorAll('.elementor-invisible').forEach((element) => element.classList.remove('elementor-invisible'));
